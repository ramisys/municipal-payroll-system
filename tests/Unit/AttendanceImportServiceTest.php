<?php

namespace Tests\Unit;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\WorkSchedule;
use App\Services\AttendanceImportException;
use App\Services\AttendanceImportService;
use Database\Seeders\AttendanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-13 · Import attendance records — FR-1.3, AC-1.3.1-1.3.5.
 * pre-oral-demonstration-plan.md §6 Table 6, W6 Track A: "AttendanceImportService
 * — all-or-nothing commit." "All-or-nothing" here means the *commit* step
 * is one transaction (a failure partway through rolls back everything);
 * AC-1.3.1 itself only requires that valid and invalid rows are separated
 * in the preview and that nothing is written before confirmation — a
 * rejected row does not block the accepted rows around it from committing
 * (FR-1.3 behavior 4, AC-1.3.2).
 */
class AttendanceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttendanceTypeSeeder::class);
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

    public function test_preview_reads_and_validates_without_writing_anything(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();

        $result = (new AttendanceImportService)->preview(base_path('tests/Fixtures/attendance_clean.xlsx'), $period);

        self::assertCount(3, $result['accepted']);
        self::assertSame([], $result['rejected']);
        self::assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_commit_writes_only_after_preview_and_reports_the_row_count(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();
        $service = new AttendanceImportService;

        self::assertSame(0, AttendanceRecord::query()->count());

        $committed = $service->commit(base_path('tests/Fixtures/attendance_clean.xlsx'), $period, null);

        self::assertSame(3, $committed);
        self::assertSame(3, AttendanceRecord::query()->count());
    }

    // AC-1.3.2, demo §7 beat 6 — the one bad row (an unknown employee
    // number) is rejected by row and reason; the other, valid rows still
    // commit.
    public function test_one_bad_row_is_rejected_by_name_while_the_valid_rows_around_it_commit(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();
        $service = new AttendanceImportService;

        $preview = $service->preview(base_path('tests/Fixtures/attendance_one_bad_row.xlsx'), $period);

        self::assertCount(3, $preview['accepted']);
        self::assertCount(1, $preview['rejected']);
        self::assertSame(5, $preview['rejected'][0]['row_number']);
        self::assertStringContainsString("'E-9999' is not on file", $preview['rejected'][0]['reason']);

        $committed = $service->commit(base_path('tests/Fixtures/attendance_one_bad_row.xlsx'), $period, null);

        self::assertSame(3, $committed);
        self::assertSame(3, AttendanceRecord::query()->count());
    }

    // AC-1.3.3 — re-importing the same cut-off replaces rather than
    // duplicates.
    public function test_reimporting_the_same_cutoff_replaces_rather_than_duplicates(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();
        $service = new AttendanceImportService;

        $service->commit(base_path('tests/Fixtures/attendance_clean.xlsx'), $period, null);
        self::assertSame(3, AttendanceRecord::query()->count());

        $service->commit(base_path('tests/Fixtures/attendance_clean.xlsx'), $period, null);
        self::assertSame(3, AttendanceRecord::query()->count());
    }

    public function test_an_employee_number_not_on_file_is_rejected_but_the_import_can_still_be_previewed(): void
    {
        $this->employeeWithSchedule('E-0001');
        $this->employeeWithSchedule('E-0002');
        $period = $this->period();

        $result = (new AttendanceImportService)->preview(base_path('tests/Fixtures/attendance_one_bad_row.xlsx'), $period);

        self::assertCount(1, $result['rejected']);
        self::assertStringContainsString('not on file', $result['rejected'][0]['reason']);
    }

    public function test_commit_throws_when_every_row_is_rejected(): void
    {
        // No employees seeded at all — every row in the fixture is rejected.
        $period = $this->period();

        $this->expectException(AttendanceImportException::class);

        (new AttendanceImportService)->commit(base_path('tests/Fixtures/attendance_clean.xlsx'), $period, null);
    }

    // AC-1.3.5 — BR-03/BR-04 derivation: an on-time day nets a full 8
    // hours with no late/undertime/overtime.
    public function test_an_on_time_day_derives_a_full_standard_day_with_no_exceptions(): void
    {
        $this->employeeWithSchedule('E-0001');
        $period = $this->period();

        $result = (new AttendanceImportService)->preview(base_path('tests/Fixtures/attendance_clean.xlsx'), $period);
        $row = collect($result['accepted'])->firstWhere('work_date', '2026-01-05');

        self::assertNotNull($row);
        self::assertSame('8.00', $row['hours_worked']);
        self::assertSame(0, $row['late_minutes']);
        self::assertSame(0, $row['undertime_minutes']);
        self::assertSame('0.00', $row['overtime_hours']);
        self::assertSame('ORDINARY', $row['day_classification']);
    }
}
