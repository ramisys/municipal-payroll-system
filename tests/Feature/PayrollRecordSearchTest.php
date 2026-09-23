<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// UC-29 · Search payroll records — FR-5.1, FR-5.2, NFR-5.5.
// AC-5.1.1 Every finalized run is retrievable in full with all inputs.
// AC-5.1.3 A payroll line displays original figures even after changes.
// AC-5.2.1 Any record is located by at least one supported criterion.
// AC-5.2.2 Search returns results within one minute (NFR-5.5).
// AC-5.2.3 Empty search result states so plainly and states criteria applied.
class PayrollRecordSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
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
            'department_code' => 'ACC',
            'department_name' => 'Accounting Department',
            'is_active' => true,
        ]);

        $pos = Position::create([
            'position_code' => 'BOOKKEEPER',
            'position_title' => 'Bookkeeper',
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

    public function test_all_four_authorized_roles_can_reach_search_records(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        foreach ([$officer, $this->approver(), $this->administrator(), $this->viewer()] as $user) {
            $response = $this->actingAs($user)->get('/payroll-records');
            $response->assertOk();
            $response->assertSee('Search payroll records');
        }

        // Unauthenticated redirected
        auth()->logout();
        $this->get('/payroll-records')->assertRedirect('/login');
    }

    public function test_partial_match_search_on_employee_number_and_name(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // Search by partial employee number '0002'
        $respNum = $this->actingAs($officer)->get('/payroll-records?q=0002');
        $respNum->assertOk();
        $respNum->assertSee('E-0002');
        $respNum->assertDontSee('E-0001');

        // Search by partial name
        $respName = $this->actingAs($officer)->get('/payroll-records?q=FirstName_E-0003');
        $respName->assertOk();
        $respName->assertSee('E-0003');
        $respName->assertDontSee('E-0001');
    }

    public function test_search_filter_by_pay_period_and_run_status(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // Filter by period ID
        $response = $this->actingAs($officer)->get("/payroll-records?payroll_period_id={$run->payroll_period_id}");
        $response->assertOk();
        $response->assertSee('E-0001');
        $response->assertSee('Matching Payroll Runs');

        // Filter by run status DRAFT
        $respDraft = $this->actingAs($officer)->get('/payroll-records?run_status=DRAFT');
        $respDraft->assertOk();
        $respDraft->assertSee('E-0001');

        // Filter by non-existent status for this run
        $respRev = $this->actingAs($officer)->get('/payroll-records?run_status=REVERSED');
        $respRev->assertOk();
        $respRev->assertSee('No payroll records match the specified search criteria');
    }

    public function test_empty_search_states_plainly_and_echoes_applied_criteria(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // AC-5.2.3: A search returning no result says so plainly and states the criteria applied
        $response = $this->actingAs($officer)->get('/payroll-records?q=NonExistentEmployee999&run_status=REVERSED');
        $response->assertOk();
        $response->assertSee('No payroll records match the specified search criteria.');
        $response->assertSee('Employee: NonExistentEmployee999');
        $response->assertSee('Run state: REVERSED');
    }

    public function test_retained_record_view_displays_original_inputs_and_figures(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $line = PayrollLine::where('payroll_run_id', $run->payroll_run_id)->firstOrFail();

        $response = $this->actingAs($officer)->get("/payroll-records/{$line->payroll_line_id}");
        $response->assertOk();
        $response->assertSee('Accounting Department');
        $response->assertSee('Work inputs & attendance summary');
        $response->assertSee('Earnings breakdown');
        $response->assertSee('Deductions breakdown');
        $response->assertSee('Net take-home pay');
        $response->assertSee(number_format((float) $line->net_pay, 2));
    }

    public function test_employee_payroll_history_from_employee_record(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        $employee = Employee::where('employee_no', 'E-0001')->firstOrFail();

        // UC-29 A2: From the employee record, user opens an employee's full payroll history
        $response = $this->actingAs($officer)->get("/employees/{$employee->employee_id}/payroll-history");
        $response->assertOk();
        $response->assertSee('Payroll History');
        $response->assertSee($employee->fullName());
        $response->assertSee("Run #{$run->payroll_run_id}");
    }

    public function test_search_results_export_to_pdf_and_excel_with_audit_trail(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // PDF Export (UC-29 A1)
        $respPdf = $this->actingAs($officer)->get('/payroll-records/export/pdf');
        $respPdf->assertOk();
        $this->assertEquals('application/pdf', $respPdf->headers->get('Content-Type'));

        // Excel Export (UC-29 A1)
        $respExcel = $this->actingAs($officer)->get('/payroll-records/export/excel');
        $respExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respExcel->headers->get('Content-Type'));

        // Check audit log for EXPORT entries
        $auditCount = AuditLog::where('action', 'EXPORT')
            ->where('entity_name', 'PayrollLine')
            ->count();
        $this->assertGreaterThanOrEqual(2, $auditCount);
    }

    public function test_search_retrieval_performance_under_one_minute(): void
    {
        $officer = $this->officer();
        $run = $this->setupRunWithImport($officer);

        // NFR-5.5 / AC-5.2.2: search returns results within one minute
        $start = microtime(true);
        $response = $this->actingAs($officer)->get('/payroll-records?q=E-0001');
        $duration = microtime(true) - $start;

        $response->assertOk();
        $this->assertLessThan(60.0, $duration);
    }
}
