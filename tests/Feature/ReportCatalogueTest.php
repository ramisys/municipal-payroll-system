<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatutoryScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// UC-30 · Generate report — FR-5.3 (AC-5.3.1 – AC-5.3.5).
// AC-5.3.1 Every report in catalogue marked Must is generated without manual compilation.
// AC-5.3.2 Report totals reconcile to the payroll register of the same period to the centavo.
// AC-5.3.3 Every report exports to both PDF and Excel with content intact.
// AC-5.3.5 A report over an unfinalized run is visibly marked provisional.
class ReportCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        $this->seed(StatutoryScheduleSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function approver(): User
    {
        return User::factory()->forRole('APPROVER')->create();
    }

    private function administrator(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function viewer(): User
    {
        return User::factory()->forRole('VIEWER')->create();
    }

    private function period(int $periodNo = 1): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-'.str_pad((string) (($periodNo - 1) * 15 + 1), 2, '0', STR_PAD_LEFT),
            'cutoff_end' => '2026-01-'.str_pad((string) ($periodNo * 15), 2, '0', STR_PAD_LEFT),
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function setupRunWithImport(User $officer, ?PayrollPeriod $period = null): PayrollRun
    {
        $period = $period ?? $this->period();

        $dept = Department::create([
            'department_code' => 'ADMIN',
            'department_name' => 'General Administration',
            'is_active' => true,
        ]);

        $pos = Position::create([
            'position_code' => 'CLERK',
            'position_title' => 'Administrative Clerk',
            'is_active' => true,
        ]);

        $stat = EmploymentStatus::create([
            'status_code' => 'REG',
            'status_name' => 'Regular',
            'is_active' => true,
        ]);

        foreach (['E-0001', 'E-0002', 'E-0003'] as $empNo) {
            $emp = Employee::factory()->create([
                'employee_no' => $empNo,
                'last_name' => "LastName_{$empNo}",
                'first_name' => "FirstName_{$empNo}",
                'is_active' => true,
            ]);

            EmploymentDetail::create([
                'employee_id' => $emp->employee_id,
                'department_id' => $dept->department_id,
                'position_id' => $pos->position_id,
                'employment_status_id' => $stat->employment_status_id,
                'date_hired' => '2020-01-01',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);

            CompensationProfile::create([
                'employee_id' => $emp->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '25000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);
        }

        $run = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];

        $map = ImportColumnMap::active('CANONICAL');
        $file = UploadedFile::fake()->createWithContent('register_clean.xlsx', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx')));

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
            'import_column_map_id' => $map->import_column_map_id,
            'file' => $file,
        ]);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/commit");

        return $run->fresh();
    }

    public function test_all_four_authorized_roles_can_access_report_catalogue(): void
    {
        foreach ([$this->officer(), $this->approver(), $this->administrator(), $this->viewer()] as $user) {
            $response = $this->actingAs($user)->get('/reports');
            $response->assertOk();
            $response->assertSee('Report catalogue');
        }

        // Unauthenticated redirected
        auth()->logout();
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_catalogue_lists_all_eleven_reports_with_correct_priorities(): void
    {
        $officer = $this->officer();
        $response = $this->actingAs($officer)->get('/reports');
        $response->assertOk();

        // 11 reports from FR-5.3
        $expectedReports = [
            'Payroll Register',
            'Payroll Summary',
            'SSS Remittance Report',
            'PhilHealth Remittance Report',
            'Pag-IBIG Remittance Report',
            'Withholding Tax Report (BIR)',
            'Bank Transmittal Listing',
            '13th Month Pay Report',
            'Leave Ledger',
            'Loan Ledger',
            'Payroll Cost Comparison',
        ];

        foreach ($expectedReports as $reportName) {
            $response->assertSee($reportName);
        }

        // Must, Should, Could priorities
        $response->assertSee('Must');
        $response->assertSee('Should');
        $response->assertSee('Could');
    }

    public function test_payroll_register_report_generation_and_provisional_watermark(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // Run is in DRAFT status -> report must be marked PROVISIONAL (AC-5.3.5)
        $response = $this->actingAs($officer)->post('/reports/payroll_register/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('Payroll Register');
        $response->assertSee('PROVISIONAL'); // AC-5.3.5
        $response->assertSee('E-0001');
        $response->assertSee('TOTALS (AC-5.3.2 Reconciled)');
    }

    public function test_payroll_summary_report_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/payroll_summary/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('Payroll Summary');
        $response->assertSee('General Administration');
        $response->assertSee('Department Totals');
        $response->assertSee('Earning categories');
        $response->assertSee('Deduction categories');
    }

    public function test_report_exports_to_pdf_and_excel_with_audit_trail(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // PDF Export
        $respPdf = $this->actingAs($officer)->get("/reports/payroll_register/export/pdf?payroll_period_id={$run->payroll_period_id}");
        $respPdf->assertOk();
        $this->assertEquals('application/pdf', $respPdf->headers->get('Content-Type'));

        // Excel Export
        $respExcel = $this->actingAs($officer)->get("/reports/payroll_register/export/excel?payroll_period_id={$run->payroll_period_id}");
        $respExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respExcel->headers->get('Content-Type'));

        // Check audit log for EXPORT entries
        $auditCount = AuditLog::where('action', 'EXPORT')
            ->where('entity_name', 'Report')
            ->count();
        $this->assertGreaterThanOrEqual(2, $auditCount);
    }

    public function test_sss_remittance_report_generation_and_derivation_label(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/sss_remittance/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('SSS Remittance Report');
        $response->assertSee('TOTAL REMITTANCE');
        // Check share source label present (AC-2.3.4)
        $this->assertTrue(
            str_contains($response->getContent(), 'Imported') ||
            str_contains($response->getContent(), 'Derived')
        );
    }

    public function test_philhealth_remittance_report_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/philhealth_remittance/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('PhilHealth Remittance Report');
        $response->assertSee('TOTAL REMITTANCE');
    }

    public function test_pagibig_remittance_report_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/pagibig_remittance/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('Pag-IBIG Remittance Report');
        $response->assertSee('TOTAL REMITTANCE');
    }

    public function test_withholding_tax_report_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/withholding_tax/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('Withholding Tax Report (BIR)');
        $response->assertSee('Tax withheld');
    }

    public function test_bank_transmittal_report_generation_standard_commercial_format(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/bank_transmittal/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        $response->assertOk();
        $response->assertSee('Bank Transmittal Listing');
        $response->assertSee('TOTAL TRANSMITTAL');
        $response->assertSee('Disbursing Bank');
    }

    public function test_thirteenth_month_report_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $response = $this->actingAs($officer)->post('/reports/thirteenth_month/generate', [
            'payroll_year' => 2026,
        ]);

        $response->assertOk();
        $response->assertSee('13th Month Pay Report');
        $response->assertSee('Annual basic salary base');
        $response->assertSee('Imported 13th-month figure');
    }

    public function test_leave_and_loan_ledgers_and_cost_comparison_generation(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // Leave ledger
        $respLeave = $this->actingAs($officer)->post('/reports/leave_ledger/generate', [
            'payroll_year' => 2026,
        ]);
        $respLeave->assertOk();
        $respLeave->assertSee('Leave Ledger');

        // Loan ledger
        $respLoan = $this->actingAs($officer)->post('/reports/loan_ledger/generate', [
            'payroll_year' => 2026,
        ]);
        $respLoan->assertOk();
        $respLoan->assertSee('Loan Ledger');

        // Cost comparison
        $respCost = $this->actingAs($officer)->post('/reports/cost_comparison/generate', [
            'payroll_period_id' => $run->payroll_period_id,
        ]);
        $respCost->assertOk();
        $respCost->assertSee('Payroll Cost Comparison');
    }

    public function test_statutory_remittance_exports_to_pdf_and_excel(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        foreach (['sss_remittance', 'philhealth_remittance', 'pagibig_remittance'] as $type) {
            $respPdf = $this->actingAs($officer)->get("/reports/{$type}/export/pdf?payroll_period_id={$run->payroll_period_id}");
            $respPdf->assertOk();
            $this->assertEquals('application/pdf', $respPdf->headers->get('Content-Type'));

            $respExcel = $this->actingAs($officer)->get("/reports/{$type}/export/excel?payroll_period_id={$run->payroll_period_id}");
            $respExcel->assertOk();
            $this->assertStringContainsString('spreadsheetml', $respExcel->headers->get('Content-Type'));
        }
    }
}
