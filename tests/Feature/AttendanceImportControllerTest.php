<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * UC-13 · Import attendance records — controller/screen surface. Row
 * validation and figure derivation are AttendanceImportServiceTest; this
 * covers the HTTP preview/confirm/cancel flow and its permission gate
 * (AC-6.2.4 — the Payroll Officer's 'attendance.import' grant, RoleSeeder).
 */
class AttendanceImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function period(): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => 1,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function employeeWithSchedule(string $employeeNo): Employee
    {
        $employee = Employee::factory()->create(['employee_no' => $employeeNo]);

        WorkSchedule::create([
            'employee_id' => $employee->employee_id,
            'standard_hours_per_day' => '8.00',
            'rest_days' => 'SAT,SUN',
            'scheduled_time_in' => '08:00:00',
            'scheduled_time_out' => '17:00:00',
            'unpaid_break_hours' => '1.00',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        return $employee;
    }

    public function test_a_payroll_officer_can_preview_then_commit_an_import(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();
        $officer = $this->officer();

        $upload = UploadedFile::fake()->createWithContent(
            'attendance.xlsx',
            file_get_contents(base_path('tests/Fixtures/attendance_clean.xlsx')),
        );

        $previewResponse = $this->actingAs($officer)->post('/attendance-import/preview', [
            'payroll_period_id' => $period->payroll_period_id,
            'file' => $upload,
        ]);

        $previewResponse->assertOk();
        $previewResponse->assertSee('3 row(s) will be committed');
        self::assertSame(0, AttendanceRecord::query()->count());

        $commitResponse = $this->actingAs($officer)->post('/attendance-import/commit');

        $commitResponse->assertRedirect(route('attendance-import.create'));
        self::assertSame(3, AttendanceRecord::query()->count());
        self::assertNotNull(AuditLog::query()->where('entity_name', 'ATTENDANCE_RECORD')->first());
    }

    public function test_committing_without_a_prior_preview_is_refused(): void
    {
        $response = $this->actingAs($this->officer())->post('/attendance-import/commit');

        $response->assertRedirect(route('attendance-import.create'));
        $response->assertSessionHasErrors('file');
    }

    public function test_cancel_discards_the_pending_preview_and_commits_nothing(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();
        $officer = $this->officer();

        $upload = UploadedFile::fake()->createWithContent(
            'attendance.xlsx',
            file_get_contents(base_path('tests/Fixtures/attendance_clean.xlsx')),
        );

        $this->actingAs($officer)->post('/attendance-import/preview', [
            'payroll_period_id' => $period->payroll_period_id,
            'file' => $upload,
        ]);

        $this->actingAs($officer)->post('/attendance-import/cancel')->assertRedirect(route('attendance-import.create'));

        $this->actingAs($officer)->post('/attendance-import/commit')->assertSessionHasErrors('file');
        self::assertSame(0, AttendanceRecord::query()->count());
    }

    // AC-6.2.4 — a role without 'attendance.import' is refused.
    public function test_a_role_without_the_grant_is_refused(): void
    {
        $viewer = User::factory()->forRole('VIEWER')->create();

        $this->actingAs($viewer)->get('/attendance-import')->assertForbidden();
    }
}
