<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Services\AttendanceImportException;
use App\Services\AttendanceImportService;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// UC-13 · Import attendance records — FR-1.3, AC-1.3.1-1.3.5. Payroll
// Officer only ('attendance.import', RoleSeeder).
//
// Two-step screen matching AttendanceImportService's own preview()/commit()
// split (AC-1.3.1 "commits nothing until the user confirms the preview"):
// preview() stores the uploaded file under storage/app/private and stashes
// its path plus the chosen period in the session; commit() re-parses that
// same stored file rather than trusting any row data the browser could
// have submitted back, then deletes the temp file either way.
class AttendanceImportController extends Controller
{
    private const SESSION_KEY = 'attendance_import_pending';

    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly AttendanceImportService $attendanceImportService,
    ) {}

    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'attendance.import');

        return view('attendance.import', ['periods' => $this->periods()]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'attendance.import');

        $data = $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,payroll_period_id'],
            'file' => ['required', 'file'],
        ]);

        $period = PayrollPeriod::query()->findOrFail($data['payroll_period_id']);
        $storedPath = $request->file('file')->store('attendance-imports', 'local');

        try {
            $result = $this->attendanceImportService->preview(Storage::disk('local')->path($storedPath), $period);
        } catch (AttendanceImportException $e) {
            Storage::disk('local')->delete($storedPath);

            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $request->session()->put(self::SESSION_KEY, [
            'path' => $storedPath,
            'payroll_period_id' => $period->payroll_period_id,
        ]);

        return view('attendance.preview', [
            'period' => $period,
            'accepted' => $result['accepted'],
            'rejected' => $result['rejected'],
            'existingCount' => $result['existing_count'],
        ]);
    }

    // UC-13 step 7-8 — commits only what is still stashed from preview(),
    // for the same period the user was shown; nothing posted by the form
    // itself is trusted as row data.
    public function commit(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'attendance.import');

        $pending = $request->session()->get(self::SESSION_KEY);
        if ($pending === null) {
            return redirect()->route('attendance-import.create')->withErrors(['file' => 'UC-13: no previewed file is pending; upload a file again.']);
        }

        $period = PayrollPeriod::query()->findOrFail($pending['payroll_period_id']);
        $absolutePath = Storage::disk('local')->path($pending['path']);

        try {
            $count = $this->attendanceImportService->commit($absolutePath, $period, $request->user()->user_id);
        } catch (AttendanceImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($pending['path']);
            $request->session()->forget(self::SESSION_KEY);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'ATTENDANCE_RECORD',
            entityId: null,
            action: 'CREATE',
            newValues: ['payroll_period_id' => $period->payroll_period_id, 'rows_committed' => $count],
        );

        return redirect()->route('attendance-import.create')->with('status', "{$count} attendance row(s) committed for {$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()}.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'attendance.import');

        $pending = $request->session()->pull(self::SESSION_KEY);
        if ($pending !== null) {
            Storage::disk('local')->delete($pending['path']);
        }

        return redirect()->route('attendance-import.create')->with('status', 'Import cancelled; nothing was committed.');
    }

    /**
     * @return Collection<int, PayrollPeriod>
     */
    private function periods()
    {
        return PayrollPeriod::query()
            ->orderByDesc('payroll_year')
            ->orderByDesc('period_no')
            ->get();
    }
}
