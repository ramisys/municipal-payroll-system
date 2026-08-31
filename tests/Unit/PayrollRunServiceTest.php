<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\RunTransition;
use App\Models\User;
use App\Services\PayrollRunException;
use App\Services\PayrollRunService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmploymentStatusSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-17 · Create payroll run — FR-2.6, BR-34. Week 7 Track B
 * (pre-oral-demonstration-plan.md §6 Table 6): the run container wired to
 * real repositories, ahead of PayrollImportServiceTest's write path.
 */
class PayrollRunServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->seed(EmploymentStatusSeeder::class);
    }

    private function service(): PayrollRunService
    {
        return new PayrollRunService;
    }

    // created_by/RUN_TRANSITION.performed_by both FK users.user_id — a
    // real seeded user, not a bare literal, per this project's tests
    // (RunTransition.performed_by is NOT NULL, unlike the *_by bookkeeping
    // columns elsewhere).
    private function actorId(): int
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create()->user_id;
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

    private function activeEmployee(string $employeeNo, ?int $departmentId = null): Employee
    {
        $employee = Employee::factory()->create(['employee_no' => $employeeNo, 'is_active' => true]);

        EmploymentDetail::create([
            'employee_id' => $employee->employee_id,
            'department_id' => $departmentId ?? Department::query()->value('department_id'),
            'position_id' => Position::query()->value('position_id'),
            'employment_status_id' => EmploymentStatus::query()->value('employment_status_id'),
            'date_hired' => '2020-01-01',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
        ]);

        return $employee;
    }

    public function test_creates_a_draft_run_covering_every_active_employee_for_all_scope(): void
    {
        $this->activeEmployee('E-0001');
        $this->activeEmployee('E-0002');
        $period = $this->period();

        $created = $this->service()->createRun($period, 'REGULAR', 'ALL', $this->actorId());

        self::assertSame('DRAFT', $created['run']->run_status);
        self::assertSame(2, $created['includedCount']);
        self::assertSame([], $created['excluded']);
        self::assertSame(2, $created['run']->employee_count);

        $transition = RunTransition::query()->where('payroll_run_id', $created['run']->payroll_run_id)->first();
        self::assertNull($transition->from_status);
        self::assertSame('DRAFT', $transition->to_status);
    }

    public function test_department_scope_includes_only_that_departments_active_employees_and_reports_the_rest_excluded(): void
    {
        $departments = Department::query()->orderBy('department_id')->pluck('department_id');
        $this->activeEmployee('E-0001', $departments[0]);
        $this->activeEmployee('E-0002', $departments[1]);
        $period = $this->period();

        $created = $this->service()->createRun($period, 'REGULAR', "DEPARTMENT:{$departments[0]}", $this->actorId());

        self::assertSame(1, $created['includedCount']);
        self::assertSame(['E-0002'], $created['excluded']);

        $line = $created['run']->lines()->count();
        self::assertSame(0, $line, 'UC-17 step 4: no PAYROLL_LINE exists until UC-18 imports a register.');
    }

    public function test_a_second_open_run_for_the_same_period_population_and_type_is_refused(): void
    {
        $this->activeEmployee('E-0001');
        $period = $this->period();
        $actorId = $this->actorId();
        $first = $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);

        try {
            $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);
            self::fail('Expected a PayrollRunException (UC-17 E1).');
        } catch (PayrollRunException $e) {
            self::assertSame($first['run']->payroll_run_id, $e->existingRunId);
        }
    }

    public function test_a_different_run_type_for_the_same_period_and_population_is_permitted(): void
    {
        $this->activeEmployee('E-0001');
        $period = $this->period();
        $actorId = $this->actorId();
        $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);

        $special = $this->service()->createRun($period, 'THIRTEENTH_MONTH', 'ALL', $actorId);

        self::assertSame('DRAFT', $special['run']->run_status);
    }

    public function test_a_cancelled_run_no_longer_blocks_a_replacement(): void
    {
        $this->activeEmployee('E-0001');
        $period = $this->period();
        $actorId = $this->actorId();
        $first = $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);

        $this->service()->cancelRun($first['run'], 'Wrong population selected', $actorId);

        $second = $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);
        self::assertSame('DRAFT', $second['run']->run_status);
    }

    public function test_cancelling_a_draft_run_records_the_transition_and_reason(): void
    {
        $this->activeEmployee('E-0001');
        $period = $this->period();
        $created = $this->service()->createRun($period, 'REGULAR', 'ALL', $this->actorId());
        $cancellingActorId = $this->actorId();

        $cancelled = $this->service()->cancelRun($created['run'], 'Wrong period selected', $cancellingActorId);

        self::assertSame('CANCELLED', $cancelled->run_status);

        $transition = RunTransition::query()
            ->where('payroll_run_id', $created['run']->payroll_run_id)
            ->where('to_status', 'CANCELLED')
            ->firstOrFail();
        self::assertSame('DRAFT', $transition->from_status);
        self::assertSame('Wrong period selected', $transition->reason);
        self::assertSame($cancellingActorId, $transition->performed_by);
    }

    // UC-17 E4.
    public function test_a_run_past_draft_cannot_be_cancelled_this_way(): void
    {
        $this->activeEmployee('E-0001');
        $period = $this->period();
        $actorId = $this->actorId();
        $created = $this->service()->createRun($period, 'REGULAR', 'ALL', $actorId);

        $run = $created['run'];
        $run->run_status = 'FOR_REVIEW';
        $run->save();

        $this->expectException(PayrollRunException::class);
        $this->service()->cancelRun($run, 'Too late', $actorId);
    }
}
