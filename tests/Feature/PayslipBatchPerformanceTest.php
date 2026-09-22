<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayslipIssuance;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

// NFR-3.5 — "Generate payslips for 30 employees in less than 5 minutes"
// (functional-requirements-specification.md, NFR-3.5 evidence row).
// Drives the real 30-employee demo population (EmployeeDemoSeeder) through
// the full governed lifecycle — import, submit, approve, finalize — then
// times the UC-27 batch PDF generation of all 30 payslips.
class PayslipBatchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function approver(): User
    {
        return User::factory()->forRole('APPROVER')->create();
    }

    /** @return array<int, string> the 30 demo employee numbers, E-1000..E-1029 */
    private function demoEmployeeNumbers(): array
    {
        return array_map(fn (int $i) => sprintf('E-%04d', 1000 + $i), range(0, 29));
    }

    /** @return int[] employee ids of the 30 demo employees, keyed by index */
    private function demoEmployeeIds(): array
    {
        return DB::table('employees')
            ->whereIn('employee_no', $this->demoEmployeeNumbers())
            ->pluck('employee_id', 'employee_no')
            ->all();
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

    private function seedAttendanceAll(PayrollPeriod $period): void
    {
        $typeId = DB::table('attendance_types')->value('attendance_type_id');

        foreach ($this->demoEmployeeIds() as $employeeId) {
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

    /**
     * @param  array<string, string>  $earnings  code => amount, non-zero only
     * @param  array<string, string>  $deductions  code => amount, non-zero only
     * @return array<int, string> the 19-column canonical row
     */
    private function buildRow(string $employeeNo, array $earnings, array $deductions): array
    {
        $earningCodes = ['BASIC', 'OT', 'NIGHT_DIFF', 'HOLIDAY_PAY', 'ALLOWANCE', 'THIRTEENTH_MONTH'];
        $deductionCodes = ['SSS', 'PHILHEALTH', 'PAGIBIG', 'WTAX', 'LOAN', 'OTHER'];
        $employerShareCodes = ['SSS', 'PHILHEALTH', 'PAGIBIG'];

        $gross = '0.00';
        $earningValues = [];
        foreach ($earningCodes as $code) {
            $amount = $earnings[$code] ?? '0.00';
            $earningValues[] = $amount;
            $gross = bcadd($gross, $amount, 2);
        }

        $totalDeductions = '0.00';
        $deductionValues = [];
        foreach ($deductionCodes as $code) {
            $amount = $deductions[$code] ?? '0.00';
            $deductionValues[] = $amount;
            $totalDeductions = bcadd($totalDeductions, $amount, 2);
        }

        $employerShareValues = array_map(fn (string $code) => $deductions[$code] ?? '0.00', $employerShareCodes);

        $net = bcsub($gross, $totalDeductions, 2);

        return [$employeeNo, ...$earningValues, ...$deductionValues, ...$employerShareValues, $gross, $totalDeductions, $net];
    }

    /** @return array<int, array<int, string>> */
    private function periodRows(): array
    {
        $rows = [];

        foreach ($this->demoEmployeeNumbers() as $i => $employeeNo) {
            $basic = (string) (20000 + ($i * 100));

            $earnings = ['BASIC' => bcadd($basic, '0', 2)];
            if ($i < 10) {
                $earnings['OT'] = number_format(500 + $i * 3.25, 2, '.', '');
            }

            $deductions = [
                'SSS' => '900.00',
                'PHILHEALTH' => '500.00',
                'PAGIBIG' => '100.00',
                'WTAX' => number_format(700 + $i * 4.10, 2, '.', ''),
            ];
            if ($i >= 20) {
                $deductions['LOAN'] = number_format(300 + $i * 2.50, 2, '.', '');
            }

            $rows[] = $this->buildRow($employeeNo, $earnings, $deductions);
        }

        return $rows;
    }

    /** @param  array<int, array<int, string>>  $rows */
    private function writeRegisterFile(string $path, array $rows): void
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

    private function acknowledgeAllWarnings(PayrollRun $run, User $actor): void
    {
        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warning) {
            $this->actingAs($actor)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warning->exception_instance_id}", [
                'acknowledgment_reason' => 'Acknowledged and verified for processing.',
            ]);
        }
    }

    public function test_batch_generate_of_thirty_payslips_under_five_minutes(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $period = $this->period();

        $this->seedAttendanceAll($period);

        $run = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];
        $this->assertSame(30, $run->employee_count);

        // Import the 30-row register.
        $path = sys_get_temp_dir().'/nfr35_register.xlsx';
        $this->writeRegisterFile($path, $this->periodRows());

        try {
            $file = UploadedFile::fake()->createWithContent('register_30.xlsx', file_get_contents($path));
            $map = ImportColumnMap::active('CANONICAL');

            $this->actingAs($officer)
                ->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
                    'import_column_map_id' => $map->import_column_map_id,
                    'file' => $file,
                ])
                ->assertOk();

            $this->actingAs($officer)
                ->post("/payroll-runs/{$run->payroll_run_id}/import/commit")
                ->assertRedirect(route('payroll-runs.show', $run));
        } finally {
            @unlink($path);
        }

        $run->refresh();
        $this->assertSame(30, $run->lines()->count());

        $this->acknowledgeAllWarnings($run, $officer);
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize")->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);

        // NFR-3.5: time the whole UC-27 batch PDF export of 30 payslips.
        $started = microtime(true);
        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/payslips/generate");
        $elapsed = microtime(true) - $started;

        $this->assertLessThan(300.0, $elapsed, sprintf('NFR-3.5: 30-payslip batch export took %.3f s (limit 300 s).', $elapsed));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertGreaterThan(0, strlen($response->getContent()));

        $this->assertSame(30, PayslipIssuance::where('payroll_run_id', $run->payroll_run_id)->where('issuance_type', 'ORIGINAL')->count());
    }
}
