<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayslipIssuance;
use App\Models\Position;
use App\Models\User;
use App\Services\PayrollRunService;
use App\Services\PayslipService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// W11 · Phase P2, Milestone P-C — Payslips (M6).
// UC-27 (FR-3.1/3.2/3.3, NFR-3.5), UC-28 (FR-3.4): generation of a
// finalized run's whole payslip set as a multi-page PDF, per-employee
// PDF view, reprint with an issuance record, and the finalized-run guard.
class PayslipGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(SystemConfigSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function approver(): User
    {
        return User::factory()->forRole('APPROVER')->create();
    }

    private function viewer(): User
    {
        return User::factory()->forRole('VIEWER')->create();
    }

    private function period(int $periodNo = 1, ?string $payDate = null): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-'.str_pad((string) (($periodNo - 1) * 15 + 1), 2, '0', STR_PAD_LEFT),
            'cutoff_end' => '2026-01-'.str_pad((string) ($periodNo * 15), 2, '0', STR_PAD_LEFT),
            'pay_date' => $payDate ?? '2026-01-20',
            'is_closed' => false,
        ]);
    }

    /**
     * Three employees (E-0001..E-0003), each with a daily in-cutoff
     * attendance record, a compensation profile, and a dated employment
     * detail so the payslip renders department/position/status. Each is
     * assigned a distinct department so the FR-3.3 filter is testable.
     */
    private function setupRun(User $actor, ?PayrollPeriod $period = null): PayrollRun
    {
        $period = $period ?? $this->period();
        $typeId = DB::table('attendance_types')->value('attendance_type_id');

        $position = Position::create([
            'position_code' => 'CLRK-III',
            'position_title' => 'Clerk III',
            'is_active' => true,
        ]);
        $status = EmploymentStatus::create([
            'status_name' => 'PERMANENT',
            'is_payroll_eligible' => true,
            'is_active' => true,
        ]);
        $departments = [];
        foreach (['A', 'B', 'C'] as $tag) {
            $departments[$tag] = Department::create([
                'department_code' => "DEPT-{$tag}",
                'department_name' => "Department {$tag}",
                'is_active' => true,
            ]);
        }

        foreach (['E-0001', 'E-0002', 'E-0003'] as $index => $empNo) {
            $emp = Employee::factory()->create([
                'employee_no' => $empNo,
                'is_active' => true,
                'sss_no' => '01-2345678-9',
                'philhealth_no' => '01-234567890-1',
                'pagibig_mid' => '1234-5678-9012',
                'tin' => '123-456-789-000',
            ]);

            CompensationProfile::create([
                'employee_id' => $emp->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '25000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);

            EmploymentDetail::create([
                'employee_id' => $emp->employee_id,
                'department_id' => $departments['ABC'[$index]]->department_id,
                'position_id' => $position->position_id,
                'employment_status_id' => $status->employment_status_id,
                'date_hired' => '2020-01-01',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);

            AttendanceRecord::create([
                'employee_id' => $emp->employee_id,
                'attendance_type_id' => $typeId,
                'work_date' => $period->cutoff_start->toDateString(),
                'hours_worked' => '8.00',
                'overtime_hours' => '0.00',
                'day_classification' => 'ORDINARY',
                'source' => 'IMPORT',
            ]);
        }

        return app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $actor->user_id)['run'];
    }

    private function acknowledgeAllWarnings(PayrollRun $run, User $actor): void
    {
        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warning) {
            $this->actingAs($actor)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warning->exception_instance_id}", [
                'acknowledgment_reason' => 'Acknowledged and verified for processing.',
            ]);
        }
    }

    private function importCleanRegister(PayrollRun $run, User $officer): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $file = UploadedFile::fake()->createWithContent('register_clean.xlsx', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx')));

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
                'import_column_map_id' => $map->import_column_map_id,
                'file' => $file,
            ])
            ->assertOk();

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/commit")
            ->assertRedirect(route('payroll-runs.show', $run));
    }

    private function finalizeRun(User $officer, User $approver, PayrollRun $run): PayrollRun
    {
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);

        return $run;
    }

    // UC-27 happy path: one POST generates the whole set as one PDF and
    // records exactly one ORIGINAL issuance per line (AC-3.1.4). A repeat
    // generation is idempotent — it records nothing new (AC-3.1.3).
    public function test_generate_batch_pdf_records_one_original_per_employee_and_is_idempotent(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertGreaterThan(0, strlen($response->getContent()));

        $this->assertSame(3, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'ORIGINAL')->count());
        $this->assertSame(0, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'REPRINT')->count());

        $audit = AuditLog::where('entity_name', 'PAYROLL_RUN')
            ->where('entity_id', $run->payroll_run_id)
            ->where('action', 'EXPORT')
            ->latest('audit_log_id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('payslip_batch', (string) $audit->new_values);

        // Idempotent regeneration (AC-3.1.4): still three ORIGINAL rows.
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")->assertOk();
        $this->assertSame(3, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'ORIGINAL')->count());
    }

    // AC-3.1.3 / AC-4.4.4: generation refused before finalization.
    public function test_generate_refused_for_unfinalized_run(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);
        $this->assertEquals('DRAFT', $run->run_status);

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")
            ->assertRedirect(route('payroll-runs.show', $run))
            ->assertSessionHasErrors('payslips');

        $this->assertSame(0, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->count());
    }

    // AC-3.1.1, FR-3.2, AC-3.2.2: every printed figure is the stored line's
    // own value; gross less total deductions prints to the centavo as net;
    // every non-zero deduction appears by name (AC-3.2.1).
    public function test_payslip_figures_derive_verbatim_from_stored_payroll_lines(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        $line = $run->lines()
            ->where('employee_id', Employee::where('employee_no', 'E-0002')->value('employee_id'))
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.position',
                'employee.employmentDetails.employmentStatus',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ])
            ->firstOrFail();

        $data = app(PayslipService::class)->payslipData($run, $line, generatedAt: $run->finalized_at);

        $this->assertSame($line->employee->employee_no, $data['employee']['no']);
        $this->assertSame($line->employee->fullName(), $data['employee']['name']);
        $this->assertSame('Department B', $data['employee']['department']);
        $this->assertSame('Clerk III', $data['employee']['position']);
        $this->assertSame('PERMANENT', $data['employee']['status']);

        $this->assertSame(PayslipService::formatAmount((string) $line->gross_pay), $data['gross_pay']);
        $this->assertSame(PayslipService::formatAmount((string) $line->total_deductions), $data['total_deductions']);
        $this->assertSame(PayslipService::formatAmount((string) $line->net_pay), $data['net_pay']);

        $this->assertSame($run->currentImport()?->version_no, $data['import_version']);

        $liveEarnings = $line->earningLines->filter(fn ($e) => bccomp((string) $e->amount, '0.00', 2) !== 0)->values();
        $this->assertSame($liveEarnings->count(), count($data['earnings']));
        foreach ($liveEarnings as $i => $earning) {
            $this->assertSame($earning->earningType?->earning_name, $data['earnings'][$i]['name']);
            $this->assertSame(PayslipService::formatAmount((string) $earning->amount), $data['earnings'][$i]['amount']);
        }

        $liveDeductions = $line->deductionLines->filter(fn ($d) => bccomp((string) $d->amount, '0.00', 2) !== 0)->values();
        $this->assertSame($liveDeductions->count(), count($data['deductions']));
        $named = array_column($data['deductions'], 'name');
        foreach ($liveDeductions as $i => $deduction) {
            // AC-3.2.1: every stored deduction prints by its deduction type name.
            $this->assertContains($deduction->deductionType?->deduction_name, $named);
            $this->assertSame(
                PayslipService::formatAmount((string) $deduction->amount),
                $data['deductions'][$i]['amount'],
            );
        }

        // AC-3.2.2: gross − deductions = net, to the centavo, as printed.
        $gross = str_replace(',', '', $data['gross_pay']);
        $deductions = str_replace(',', '', $data['total_deductions']);
        $net = str_replace(',', '', $data['net_pay']);
        $this->assertSame(0, bccomp(bcsub($gross, $deductions, 2), $net, 2));
    }

    // UC-28 step 3 / AC-3.3.3: the single-payslip PDF is a read-only render
    // — the file name carries employee + period and nothing is recorded.
    public function test_single_pdf_view_is_read_only_and_named_employee_period(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        $employee = Employee::where('employee_no', 'E-0001')->firstOrFail();

        $response = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/payslips/{$employee->employee_id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('E-0001-2026-p1.pdf', $response->headers->get('content-disposition') ?? '');

        // Read-only: no issuance row, no audit entry, no REPRINT record.
        $this->assertSame(0, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->count());
        $this->assertSame(0, AuditLog::where('entity_name', 'PAYSLIP_ISSUANCE')->count());
    }

    // UC-28: a reprint is regenerated from the stored line (never a saved
    // file), keeps every figure identical to the original (AC-3.4.2), and
    // records one REPRINT row with user and timestamp (AC-3.4.3).
    public function test_reprint_records_issuance_and_keeps_original_figures(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        // Generate originals first, then reprint the same employee.
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")->assertOk();

        $employee = Employee::where('employee_no', 'E-0003')->firstOrFail();
        $line = $run->lines()->where('employee_id', $employee->employee_id)
            ->with('employee.employmentDetails', 'earningLines.earningType', 'deductionLines.deductionType')
            ->firstOrFail();

        $service = app(PayslipService::class);
        $original = $service->payslipData($run, $line, generatedAt: $run->finalized_at);

        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/{$employee->employee_id}/reprint");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));

        $reprint = PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)
            ->where('employee_id', $employee->employee_id)
            ->where('issuance_type', 'REPRINT')
            ->first();
        $this->assertNotNull($reprint);
        $this->assertSame($officer->user_id, $reprint->issued_by);
        $this->assertNotNull($reprint->issued_at);

        // AC-3.4.3 audit event.
        $audit = AuditLog::where('entity_name', 'PAYSLIP_ISSUANCE')
            ->where('entity_id', $reprint->payslip_issuance_id)
            ->where('action', 'EXPORT')
            ->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('payslip_reprint', (string) $audit->new_values);

        // AC-3.4.2: values identical to the original render.
        $reprinted = $service->payslipData($run, $line, generatedAt: $run->finalized_at, isReprint: true, reprintedAt: $reprint->issued_at, reprintedBy: 'Payroll Officer');
        $this->assertSame($original['gross_pay'], $reprinted['gross_pay']);
        $this->assertSame($original['total_deductions'], $reprinted['total_deductions']);
        $this->assertSame($original['net_pay'], $reprinted['net_pay']);
        $this->assertSame($original['earnings'], $reprinted['earnings']);
        $this->assertSame($original['deductions'], $reprinted['deductions']);
        $this->assertTrue($reprinted['is_reprint']);
    }

    // FR-3.3 behavior 1 / FR-6.2: a department filter limits the batch to
    // that department's lines, and non-PO roles cannot generate payslips.
    public function test_department_filter_limits_batch_and_only_po_can_generate(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $viewer = $this->viewer();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        // Filter to Department B (E-0002): one payslip, one ORIGINAL row.
        $deptBId = Department::where('department_code', 'DEPT-B')->value('department_id');

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate", ['department_id' => $deptBId])
            ->assertOk();

        $this->assertSame(1, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'ORIGINAL')->count());

        // Unfiltered regeneration backfills the other two (idempotent + fill-in).
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")->assertOk();
        $this->assertSame(3, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'ORIGINAL')->count());

        // FR-6.2: Approver and Viewer read payslips but do not generate.
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate")
            ->assertForbidden();

        // Viewer-read: the single payslip PDF is viewable by the Viewer role.
        $employee = Employee::where('employee_no', 'E-0002')->firstOrFail();
        $this->actingAs($viewer)
            ->get("/payroll-runs/{$run->payroll_run_id}/payslips/{$employee->employee_id}/pdf")
            ->assertOk();
    }

    // A payslip request for an employee outside the run is a 404, not a
    // document (UC-28 the pair must belong to the run).
    public function test_payslip_for_employee_outside_run_is_404(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $run = $this->finalizeRun($officer, $approver, $run);

        $outsider = Employee::factory()->create(['employee_no' => 'E-9999', 'is_active' => true]);

        $this->actingAs($officer)
            ->get("/payroll-runs/{$run->payroll_run_id}/payslips/{$outsider->employee_id}/pdf")
            ->assertNotFound();

        $this->assertSame(0, PayslipIssuance::where('employee_id', $outsider->employee_id)->count());
    }
}
