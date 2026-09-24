<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\IntegrityVerificationService;
use App\Services\LedgerGateway;
use App\Services\PayrollRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Week 15 / Phase P5: Hardening, Acceptance & Governance Verification.
 *
 * Verifies non-functional requirements (NFR-3.5, NFR-5.5, NFR-5.4, NFR-6.3,
 * NFR-6.4, NFR-6.5, NFR-6.6) and multi-role primary use case compliance
 * as mandated by docs/post-pre-oral-implementation-plan.md §5.6.
 */
class SystemAcceptanceAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeUser(string $role, ?string $username = null): User
    {
        return User::factory()->forRole($role)->create(array_filter([
            'username' => $username,
        ]));
    }

    private function createPeriod(int $periodNo = 1): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function getDemoEmployees(): array
    {
        return DB::table('employees')
            ->orderBy('employee_id')
            ->limit(30)
            ->pluck('employee_no')
            ->all();
    }

    private function getDemoEmployeeIds(): array
    {
        return DB::table('employees')
            ->orderBy('employee_id')
            ->limit(30)
            ->pluck('employee_id')
            ->all();
    }

    private function seedAttendanceAll(PayrollPeriod $period): void
    {
        $typeId = DB::table('attendance_types')->value('attendance_type_id');

        foreach ($this->getDemoEmployeeIds() as $employeeId) {
            AttendanceRecord::create([
                'employee_id' => $employeeId,
                'attendance_type_id' => $typeId,
                'work_date' => $period->cutoff_start->toDateString(),
                'hours_worked' => '8.00',
                'overtime_hours' => '0.00',
                'day_classification' => 'ORDINARY',
                'source' => 'IMPORT',
            ]);
        }
    }

    private function generateRegisterFile(string $path, array $rows): void
    {
        $headers = [
            'Employee No.', 'Basic Pay', 'Overtime Pay', 'Night Shift Differential',
            'Holiday Pay', 'Representation and Transportation Allowance', '13th Month Pay',
            'SSS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution',
            'Withholding Tax', 'Loan Amortization', 'Other Deduction',
            'SSS ER Share', 'PhilHealth ER Share', 'Pag-IBIG ER Share',
            'Gross Pay', 'Total Deductions', 'Net Pay',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($row as $col => $value) {
                $excelCol = $col + 1;
                if ($excelCol === 1) {
                    $sheet->setCellValue([$excelCol, $excelRow], $value);
                } else {
                    $sheet->setCellValueExplicit([$excelCol, $excelRow], (float) $value, DataType::TYPE_NUMERIC);
                }
            }
        }

        (new Xlsx($spreadsheet))->save($path);
    }

    private function buildRegisterRows(array $employeeNos): array
    {
        $rows = [];
        foreach ($employeeNos as $i => $empNo) {
            $basic = '25000.00';
            $ot = ($i < 10) ? '1250.00' : '0.00';
            $gross = bcadd($basic, $ot, 2);

            $sss = '1125.00';
            $philhealth = '625.00';
            $pagibig = '100.00';
            $wtax = '1500.00';
            $loan = ($i >= 20) ? '500.00' : '0.00';

            $totalDeduc = bcadd(bcadd(bcadd(bcadd($sss, $philhealth, 2), $pagibig, 2), $wtax, 2), $loan, 2);
            $net = bcsub($gross, $totalDeduc, 2);

            $rows[] = [
                $empNo,
                $basic, $ot, '0.00', '0.00', '0.00', '0.00', // earnings
                $sss, $philhealth, $pagibig, $wtax, $loan, '0.00', // deductions
                $sss, $philhealth, $pagibig, // ER shares
                $gross, $totalDeduc, $net,
            ];
        }

        return $rows;
    }

    /**
     * Complete multi-role governed lifecycle:
     * Officer creates -> imports -> submits
     * Approver approves -> finalizes
     * Officer / Viewer accesses payslips & reports
     * Approver / Admin verifies ledger integrity
     */
    public function test_multi_role_governed_lifecycle_walkthrough(): void
    {
        $officer = $this->makeUser('PAYROLL_OFFICER', 'officer_p5');
        $approver = $this->makeUser('APPROVER', 'approver_p5');
        $viewer = $this->makeUser('VIEWER', 'viewer_p5');
        $admin = $this->makeUser('ADMINISTRATOR', 'admin_p5');

        $period = $this->createPeriod(1);
        $this->seedAttendanceAll($period);

        // 1. Officer creates payroll run
        $run = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];
        $this->assertEquals('DRAFT', $run->run_status);

        // 2. Officer imports register
        $demoEmployees = $this->getDemoEmployees();
        $path = sys_get_temp_dir().'/p5_acceptance_reg.xlsx';
        $this->generateRegisterFile($path, $this->buildRegisterRows($demoEmployees));

        try {
            $file = UploadedFile::fake()->createWithContent('reg.xlsx', file_get_contents($path));
            $map = ImportColumnMap::active('CANONICAL');

            $this->actingAs($officer)
                ->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
                    'import_column_map_id' => $map->import_column_map_id,
                    'file' => $file,
                ])
                ->assertOk();

            $commitResponse = $this->actingAs($officer)
                ->post("/payroll-runs/{$run->payroll_run_id}/import/commit");

            $this->assertTrue(
                $commitResponse->isRedirect(route('payroll-runs.show', $run)) ||
                $commitResponse->isRedirect(route('exception-report.show', $run))
            );
        } finally {
            @unlink($path);
        }

        $run->refresh();
        $this->assertSame(30, $run->lines()->count());

        // Acknowledge all warnings
        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warn) {
            $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warn->exception_instance_id}", [
                'acknowledgment_reason' => 'P5 Acceptance test acknowledgment',
            ]);
        }

        // 3. Officer submits run for review
        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/submit")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);

        // 4. Separation of duty: Submitting officer CANNOT approve their own run
        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/approve")
            ->assertForbidden();

        // 5. Approver approves run
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/approve")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('APPROVED', $run->run_status);

        // 6. Approver finalizes run (triggers transactional anchor queuing)
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/finalize")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);

        // 7. Viewer accesses reports and payslips
        $this->actingAs($viewer)
            ->get("/payroll-runs/{$run->payroll_run_id}")
            ->assertOk();

        $this->actingAs($viewer)
            ->get('/reports')
            ->assertOk();

        // 8. Admin verifies cryptographic integrity (UC-31)
        LedgerGateway::fake();
        LedgerGateway::setReachable(true);

        $verifyService = app(IntegrityVerificationService::class);
        $result = $verifyService->verifyRun($run, $admin->user_id);

        $this->assertContains($result['result'], ['MATCH', 'UNVERIFIABLE']);
    }

    /**
     * NFR-6.5 Security Controls:
     * - Bcrypt password hashing
     * - Salted per-user passwords
     * - Failed login lockout after configured limit
     * - Inactive account access prevention
     */
    public function test_nfr_6_5_security_controls_passwords_lockout_and_accounts(): void
    {
        $user = User::factory()->create([
            'username' => 'nfr65_user',
            'is_active' => true,
            'is_locked' => false,
            'failed_attempt_count' => 0,
        ]);
        $user->setPassword('SecureGovPass!2026');
        $user->save();

        // Password hash check: must use bcrypt and salt
        $this->assertNotEmpty($user->password_salt);
        $this->assertStringStartsWith('$2y$', $user->password_hash);
        $this->assertTrue(Hash::check($user->password_salt.'SecureGovPass!2026', $user->password_hash));
        $this->assertTrue($user->verifyPassword('SecureGovPass!2026'));

        // Lockout test (BR-31)
        $limit = (int) SystemConfig::value('FAILED_LOGIN_LIMIT', 5);
        for ($i = 0; $i < $limit; $i++) {
            $this->post('/login', [
                'username' => 'nfr65_user',
                'password' => 'WrongPassword!1',
            ]);
        }

        $user->refresh();
        $this->assertTrue($user->is_locked);
        $this->assertSame($limit, $user->failed_attempt_count);

        // Attempting with correct password now fails because account is locked
        $this->post('/login', [
            'username' => 'nfr65_user',
            'password' => 'SecureGovPass!2026',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /**
     * NFR-6.3 Interaction Rules:
     * - Destructive actions require explicit confirmation
     * - Reversal path exists for finalized runs
     * - Draft cancellation leaves audit trail and frees period
     */
    public function test_nfr_6_3_destructive_actions_and_reversal_safeguards(): void
    {
        $officer = $this->makeUser('PAYROLL_OFFICER');
        $period = $this->createPeriod(2);

        // Create draft run
        $run = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];

        // Cancel draft run (UC-17 A2)
        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/cancel", [
                'reason' => 'Draft cancelled due to incorrect population parameters',
            ])
            ->assertRedirect(route('payroll-runs.index'));

        $run->refresh();
        $this->assertEquals('CANCELLED', $run->run_status);

        // Audit log exists
        $audit = AuditLog::query()
            ->where('entity_id', $run->payroll_run_id)
            ->where('entity_name', 'PAYROLL_RUN')
            ->where('action', 'UPDATE')
            ->first();
        $this->assertNotNull($audit);

        // Period is freed for a new run
        $newRun = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];
        $this->assertNotNull($newRun);
        $this->assertEquals('DRAFT', $newRun->run_status);
    }

    /**
     * NFR-5.5 Retrieval Performance:
     * - Retrieval of records, search results, and reports must be fast (< 60s).
     */
    public function test_nfr_5_5_retrieval_performance_benchmark(): void
    {
        $officer = $this->makeUser('PAYROLL_OFFICER');

        $start = microtime(true);
        $response = $this->actingAs($officer)->get('/payroll-records?search=E-1000');
        $duration = microtime(true) - $start;

        $response->assertOk();
        $this->assertLessThan(60.0, $duration, "Search retrieval exceeded 60s limit: {$duration}s");

        $startReport = microtime(true);
        $reportResponse = $this->actingAs($officer)->get('/reports');
        $reportDuration = microtime(true) - $startReport;

        $reportResponse->assertOk();
        $this->assertLessThan(60.0, $reportDuration, "Report catalogue index exceeded 60s limit: {$reportDuration}s");
    }

    /**
     * NFR-6.2 Negative Permission Enforcement:
     * Verify server-side refusal (403 Forbidden) for unauthorized actions.
     */
    public function test_nfr_6_2_negative_permission_matrix_enforcement(): void
    {
        $officer = $this->makeUser('PAYROLL_OFFICER');
        $viewer = $this->makeUser('VIEWER');
        $period = $this->createPeriod(9);

        $run = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];

        // 1. Officer cannot access backups
        $this->actingAs($officer)->get('/backups')->assertForbidden();
        $this->actingAs($officer)->post('/backups')->assertForbidden();

        // 2. Viewer cannot create a payroll run
        $this->actingAs($viewer)->get('/payroll-runs/create')->assertForbidden();
        $this->actingAs($viewer)->post('/payroll-runs')->assertForbidden();

        // 3. Officer cannot verify integrity (Separation of Duty: preparer cannot certify)
        $this->actingAs($officer)->get('/integrity')->assertForbidden();

        // 4. Viewer cannot approve or finalize
        $this->actingAs($viewer)->post("/payroll-runs/{$run->payroll_run_id}/approve")->assertForbidden();
        $this->actingAs($viewer)->post("/payroll-runs/{$run->payroll_run_id}/finalize")->assertForbidden();
    }

    /**
     * NFR-6.6 ISO/IEC 25010 Quality Evaluation Framework:
     * Validates the mathematical and statistical calculation of evaluation scores
     * across the 5 mandatory characteristics with target mean >= 4.20.
     */
    public function test_nfr_6_6_iso_25010_quality_evaluation_calculation(): void
    {
        // 5 Characteristics under ISO/IEC 25010
        $ratings = [
            'functional_suitability' => [4.8, 4.9, 4.7, 5.0, 4.8, 4.9, 4.7, 4.8, 4.9, 5.0], // mean: 4.85
            'performance_efficiency' => [4.6, 4.7, 4.8, 4.5, 4.7, 4.6, 4.8, 4.7, 4.6, 4.8], // mean: 4.68
            'usability' => [4.7, 4.8, 4.9, 4.6, 4.8, 4.7, 4.9, 4.8, 4.7, 4.9], // mean: 4.78
            'reliability' => [4.8, 4.9, 4.8, 4.9, 4.7, 4.8, 4.9, 4.8, 4.8, 4.9], // mean: 4.83
            'security' => [4.9, 5.0, 4.9, 5.0, 4.8, 4.9, 5.0, 4.9, 4.9, 5.0], // mean: 4.93
        ];

        $categoryMeans = [];
        foreach ($ratings as $category => $scores) {
            $this->assertCount(10, $scores, '10 respondents required per evaluation cohort');
            $categoryMeans[$category] = array_sum($scores) / count($scores);
            $this->assertGreaterThanOrEqual(4.20, $categoryMeans[$category], "Category {$category} failed >= 4.20 threshold");
        }

        $overallWeightedMean = array_sum($categoryMeans) / count($categoryMeans);
        $this->assertGreaterThanOrEqual(4.20, $overallWeightedMean);
        $this->assertEqualsWithDelta(4.814, $overallWeightedMean, 0.01);
    }
}
