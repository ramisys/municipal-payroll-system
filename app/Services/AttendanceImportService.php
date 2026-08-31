<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

// UC-13 · Import attendance records — FR-1.3, BR-03, BR-04, BR-09,
// AC-1.3.1-1.3.5. Week 6 Track A: "AttendanceImportService — all-or-nothing
// commit" (pre-oral-demonstration-plan.md §6 Table 6).
//
// Two-phase like the register import screen's intended shape (AC-1.3.1
// "commits nothing until the user confirms the preview"): preview() reads
// and validates every row without writing anything; commit() re-parses the
// same stored file and writes only inside one transaction, so a failure
// partway through rolls back everything rather than leaving a partial
// import (A1's "not even the accepted rows" extended to "not even on
// error").
//
// The template's columns are fixed (FR-1.3 behavior 1), unlike the
// register's AD-17 configurable mapping — attendance intake has no
// equivalent open item naming a variable third-party layout.
class AttendanceImportService
{
    public const HEADER_EMPLOYEE_NO = 'Employee No';

    public const HEADER_DATE = 'Date';

    public const HEADER_TIME_IN = 'Time In';

    public const HEADER_TIME_OUT = 'Time Out';

    /**
     * @return array{
     *     accepted: array<int, array{row_number: int, employee_id: int, employee_no: string, work_date: string, time_in: string, time_out: string, hours_worked: string, late_minutes: int, undertime_minutes: int, overtime_hours: string, night_diff_hours: string, day_classification: string}>,
     *     rejected: array<int, array{row_number: int, reason: string}>,
     *     existing_count: int,
     * }
     */
    public function preview(string $filePath, PayrollPeriod $period): array
    {
        $ordinaryType = AttendanceType::query()->where('attendance_code', 'ORDINARY')->first();

        if ($ordinaryType === null) {
            throw new AttendanceImportException("Reference data missing: attendance type 'ORDINARY' must be seeded before an import can run.");
        }

        $sheet = $this->loadSheet($filePath);
        $headerIndex = $this->indexHeaderRow($sheet);
        $columns = $this->resolveColumns($headerIndex);

        $highestDataRow = $sheet->getHighestDataRow();

        if ($highestDataRow < 2) {
            throw new AttendanceImportException('The file contains a header row but no data rows.');
        }

        $accepted = [];
        $rejected = [];

        for ($row = 2; $row <= $highestDataRow; $row++) {
            $employeeNo = trim((string) $sheet->getCell([$columns['employee_no'], $row])->getFormattedValue());
            $dateRaw = trim((string) $sheet->getCell([$columns['date'], $row])->getFormattedValue());
            $timeInRaw = trim((string) $sheet->getCell([$columns['time_in'], $row])->getFormattedValue());
            $timeOutRaw = trim((string) $sheet->getCell([$columns['time_out'], $row])->getFormattedValue());

            if ($employeeNo === '' && $dateRaw === '') {
                // A wholly blank trailing row inside getHighestDataRow()'s
                // range (can happen after Excel round-tripping) — skip
                // rather than reject it as data.
                continue;
            }

            try {
                $accepted[] = $this->validateRow($row, $employeeNo, $dateRaw, $timeInRaw, $timeOutRaw, $period, $ordinaryType->attendance_type_id);
            } catch (AttendanceRowRejected $e) {
                $rejected[] = ['row_number' => $row, 'reason' => $e->getMessage()];
            }
        }

        $employeeIds = array_column($accepted, 'employee_id');
        $existingCount = $employeeIds === [] ? 0 : AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$period->cutoff_start->toDateString(), $period->cutoff_end->toDateString()])
            ->count();

        return ['accepted' => $accepted, 'rejected' => $rejected, 'existing_count' => $existingCount];
    }

    /**
     * UC-13 step 7-8 — commits the accepted rows from a re-parse of the
     * same file (never trusts client-submitted row data), replacing rather
     * than duplicating a prior import for the same employee/date
     * (AC-1.3.3, unique(employee_id, work_date)).
     *
     * @return int rows committed
     */
    public function commit(string $filePath, PayrollPeriod $period, ?int $actorUserId): int
    {
        $result = $this->preview($filePath, $period);

        if ($result['accepted'] === []) {
            throw new AttendanceImportException('UC-13 E5: every row was rejected; nothing can be committed.');
        }

        return DB::transaction(function () use ($result, $actorUserId) {
            $count = 0;

            foreach ($result['accepted'] as $row) {
                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $row['employee_id'], 'work_date' => $row['work_date']],
                    [
                        'attendance_type_id' => $row['attendance_type_id'],
                        'time_in' => $row['time_in'],
                        'time_out' => $row['time_out'],
                        'hours_worked' => $row['hours_worked'],
                        'late_minutes' => $row['late_minutes'],
                        'undertime_minutes' => $row['undertime_minutes'],
                        'overtime_hours' => $row['overtime_hours'],
                        'night_diff_hours' => $row['night_diff_hours'],
                        'day_classification' => $row['day_classification'],
                        'source' => 'IMPORT',
                        'updated_by' => $actorUserId,
                        'created_by' => $actorUserId,
                    ],
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * @return array{row_number: int, employee_id: int, employee_no: string, work_date: string, time_in: string, time_out: string, hours_worked: string, late_minutes: int, undertime_minutes: int, overtime_hours: string, night_diff_hours: string, day_classification: string, attendance_type_id: int}
     *
     * @throws AttendanceRowRejected
     */
    private function validateRow(int $row, string $employeeNo, string $dateRaw, string $timeInRaw, string $timeOutRaw, PayrollPeriod $period, int $ordinaryTypeId): array
    {
        if ($employeeNo === '') {
            throw new AttendanceRowRejected("Row {$row}: employee number is blank.");
        }

        // UC-13 E2 — employee number exists and is active.
        $employee = Employee::query()->where('employee_no', $employeeNo)->first();
        if ($employee === null) {
            throw new AttendanceRowRejected("Row {$row}: employee number '{$employeeNo}' is not on file.");
        }
        if (! $employee->is_active) {
            throw new AttendanceRowRejected("Row {$row}: employee '{$employeeNo}' is not active.");
        }

        if ($dateRaw === '' || ! $this->isParseableDate($dateRaw)) {
            throw new AttendanceRowRejected("Row {$row}: '{$dateRaw}' is not a valid date.");
        }
        $workDate = Carbon::parse($dateRaw)->toDateString();

        // UC-13 E3 / BR-09 — date falls within the target cut-off.
        if ($workDate < $period->cutoff_start->toDateString() || $workDate > $period->cutoff_end->toDateString()) {
            throw new AttendanceRowRejected("Row {$row}: {$workDate} falls outside the selected cut-off ({$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()}).");
        }

        if ($timeInRaw === '' || $timeOutRaw === '' || ! $this->isParseableTime($timeInRaw) || ! $this->isParseableTime($timeOutRaw)) {
            throw new AttendanceRowRejected("Row {$row}: time in/time out must both be present and valid.");
        }

        $timeIn = Carbon::parse($timeInRaw);
        $timeOut = Carbon::parse($timeOutRaw);

        // UC-13 E4.
        if (! $timeOut->greaterThan($timeIn)) {
            throw new AttendanceRowRejected("Row {$row}: time out ({$timeOutRaw}) is not later than time in ({$timeInRaw}).");
        }

        $schedule = $this->scheduleFor($employee, $workDate);
        if ($schedule === null) {
            throw new AttendanceRowRejected("Row {$row}: employee '{$employeeNo}' has no work schedule on file for {$workDate}.");
        }

        $derived = $this->deriveFigures($timeIn, $timeOut, $schedule, $workDate);

        return [
            'row_number' => $row,
            'employee_id' => $employee->employee_id,
            'employee_no' => $employeeNo,
            'work_date' => $workDate,
            'time_in' => $timeIn->format('H:i:s'),
            'time_out' => $timeOut->format('H:i:s'),
            'attendance_type_id' => $ordinaryTypeId,
            ...$derived,
        ];
    }

    private function scheduleFor(Employee $employee, string $workDate): ?WorkSchedule
    {
        return WorkSchedule::query()
            ->where('employee_id', $employee->employee_id)
            ->where('effective_from', '<=', $workDate)
            ->where(function ($q) use ($workDate) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $workDate);
            })
            ->latest('effective_from')
            ->first();
    }

    /**
     * BR-03, BR-04 — hours worked, late/undertime minutes, overtime, and
     * night differential, derived from the raw punches against the
     * employee's schedule. Night differential (22:00-06:00 overlap) and
     * the holiday/rest-day day_classification are not given an explicit
     * formula anywhere in the baseline documents; the rule applied here
     * (standard NCR practice, no unpaid-break proration) is a documented
     * simplifying assumption, the AD-17 kind of default this project
     * accepts when an input is open (OI-04) rather than blocking on it.
     *
     * @return array{hours_worked: string, late_minutes: int, undertime_minutes: int, overtime_hours: string, night_diff_hours: string, day_classification: string}
     */
    private function deriveFigures(Carbon $timeIn, Carbon $timeOut, WorkSchedule $schedule, string $workDate): array
    {
        $grossHours = $timeIn->diffInMinutes($timeOut) / 60;
        $hoursWorked = max(0.0, $grossHours - (float) $schedule->unpaid_break_hours);

        $scheduledIn = Carbon::parse($schedule->scheduled_time_in);
        $scheduledOut = Carbon::parse($schedule->scheduled_time_out);

        $lateMinutes = max(0, (int) round($scheduledIn->diffInMinutes($timeIn, false)));
        $undertimeMinutes = max(0, (int) round($timeOut->diffInMinutes($scheduledOut, false)));

        $standardHours = (float) $schedule->standard_hours_per_day;
        $overtimeHours = max(0.0, $hoursWorked - $standardHours);

        $nightDiffHours = $this->nightDifferentialHours($timeIn, $timeOut);

        $dayClassification = $this->classifyDay($workDate, $schedule);

        return [
            'hours_worked' => number_format($hoursWorked, 2, '.', ''),
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_hours' => number_format($overtimeHours, 2, '.', ''),
            'night_diff_hours' => number_format($nightDiffHours, 2, '.', ''),
            'day_classification' => $dayClassification,
        ];
    }

    private function nightDifferentialHours(Carbon $timeIn, Carbon $timeOut): float
    {
        $nightStart = $timeIn->copy()->setTime(22, 0);
        $nightEnd = $timeIn->copy()->setTime(24, 0);
        $overlapLateEvening = $this->overlapHours($timeIn, $timeOut, $nightStart, $nightEnd);

        $morningStart = $timeIn->copy()->setTime(0, 0);
        $morningEnd = $timeIn->copy()->setTime(6, 0);
        $overlapEarlyMorning = $this->overlapHours($timeIn, $timeOut, $morningStart, $morningEnd);

        return $overlapLateEvening + $overlapEarlyMorning;
    }

    private function overlapHours(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): float
    {
        $start = $aStart->greaterThan($bStart) ? $aStart : $bStart;
        $end = $aEnd->lessThan($bEnd) ? $aEnd : $bEnd;

        return $end->greaterThan($start) ? $start->diffInMinutes($end) / 60 : 0.0;
    }

    private function classifyDay(string $workDate, WorkSchedule $schedule): string
    {
        $holiday = Holiday::query()->where('holiday_date', $workDate)->first();
        $isRestDay = in_array(strtoupper(Carbon::parse($workDate)->format('D')), $schedule->restDayList(), true);

        if ($holiday !== null && $holiday->holiday_type === 'REGULAR') {
            return $isRestDay ? 'REST_DAY_REGULAR_HOLIDAY' : 'REGULAR_HOLIDAY';
        }

        if ($holiday !== null && $holiday->holiday_type === 'SPECIAL_NON_WORKING') {
            return $isRestDay ? 'REST_DAY_SPECIAL' : 'SPECIAL_NON_WORKING';
        }

        return $isRestDay ? 'REST_DAY' : 'ORDINARY';
    }

    private function isParseableDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function isParseableTime(string $value): bool
    {
        return preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s?([AaPp][Mm])?$/', $value) === 1 && $this->isParseableDate($value);
    }

    private function loadSheet(string $filePath): Worksheet
    {
        if (! is_file($filePath)) {
            throw new AttendanceImportException("Attendance file not found: {$filePath}");
        }

        try {
            return IOFactory::load($filePath)->getActiveSheet();
        } catch (Throwable $e) {
            throw new AttendanceImportException("UC-13 E1: the file could not be read as a spreadsheet or CSV. Expected columns: 'Employee No', 'Date', 'Time In', 'Time Out'. ({$e->getMessage()})");
        }
    }

    /**
     * @return array<string, int>
     */
    private function indexHeaderRow(Worksheet $sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $index = [];

        for ($col = 1; $col <= $highestColumn; $col++) {
            $header = trim((string) $sheet->getCell([$col, 1])->getFormattedValue());

            if ($header !== '') {
                $index[$header] = $col;
            }
        }

        if ($index === []) {
            throw new AttendanceImportException('UC-13 E1: the file has no header row.');
        }

        return $index;
    }

    /**
     * @param  array<string, int>  $headerIndex
     * @return array{employee_no: int, date: int, time_in: int, time_out: int}
     */
    private function resolveColumns(array $headerIndex): array
    {
        $resolve = function (string $header) use ($headerIndex): int {
            if (! array_key_exists($header, $headerIndex)) {
                throw new AttendanceImportException("UC-13 E1: required column '{$header}' was not found. Expected columns: 'Employee No', 'Date', 'Time In', 'Time Out'.");
            }

            return $headerIndex[$header];
        };

        return [
            'employee_no' => $resolve(self::HEADER_EMPLOYEE_NO),
            'date' => $resolve(self::HEADER_DATE),
            'time_in' => $resolve(self::HEADER_TIME_IN),
            'time_out' => $resolve(self::HEADER_TIME_OUT),
        ];
    }
}
