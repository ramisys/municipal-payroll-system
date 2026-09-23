<?php

namespace App\Http\Controllers;

use App\Models\DeductionLine;
use App\Models\StatutoryBracket;
use App\Models\StatutorySchedule;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\StatutoryScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

// UC-05 · Maintain statutory schedules — FR-2.3, BR-14, BR-20.
// Primary actor: Administrator ('statutory_tables.manage')
// Secondary actor: Viewer ('statutory_tables.view' - read-only per UC-05 A3)
class StatutoryScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly StatutoryScheduleService $statutoryScheduleService,
    ) {}

    /**
     * Display schedules grouped by agency.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $this->authorizationService->can($user, 'statutory_tables.view') &&
            ! $this->authorizationService->can($user, 'statutory_tables.manage')) {
            abort(403, 'You do not have permission to view statutory schedules.');
        }

        $canManage = $this->authorizationService->can($user, 'statutory_tables.manage');
        $activeAgency = strtoupper($request->query('agency', 'SSS'));

        if (! in_array($activeAgency, StatutorySchedule::AGENCIES, true)) {
            $activeAgency = 'SSS';
        }

        $schedules = $this->statutoryScheduleService->getSchedulesForAgency($activeAgency);

        return view('statutory-schedules.index', [
            'agencies' => StatutorySchedule::AGENCIES,
            'activeAgency' => $activeAgency,
            'schedules' => $schedules,
            'canManage' => $canManage,
        ]);
    }

    /**
     * Show creation form for a new schedule version.
     */
    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'statutory_tables.manage');

        $agency = strtoupper($request->query('agency', 'SSS'));
        if (! in_array($agency, StatutorySchedule::AGENCIES, true)) {
            $agency = 'SSS';
        }

        return view('statutory-schedules.create', [
            'agency' => $agency,
            'agencies' => StatutorySchedule::AGENCIES,
        ]);
    }

    /**
     * Store new schedule version and its brackets (UC-05).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizationService->authorize($user, 'statutory_tables.manage');

        $validated = $request->validate([
            'agency' => ['required', 'string', 'in:SSS,PHILHEALTH,PAGIBIG,BIR'],
            'schedule_version' => ['required', 'string', 'max:50'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'pay_frequency' => ['nullable', 'string', 'in:MONTHLY,SEMI_MONTHLY'],
            'premium_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'salary_floor' => ['nullable', 'numeric', 'min:0'],
            'salary_ceiling' => ['nullable', 'numeric', 'min:0'],
            'compensation_cap' => ['nullable', 'numeric', 'min:0'],
            'issuance_reference' => ['nullable', 'string', 'max:255'],
            'brackets' => ['nullable', 'array'],
            'brackets.*.range_from' => ['required_with:brackets', 'numeric', 'min:0'],
            'brackets.*.range_to' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employee_share' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employer_share' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.base_tax' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.marginal_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        // Validate contiguous brackets if supplied (UC-05 E2)
        $rawBrackets = $validated['brackets'] ?? [];
        if (! empty($rawBrackets)) {
            try {
                $this->statutoryScheduleService->validateContiguousBrackets($rawBrackets);
            } catch (InvalidArgumentException $e) {
                return redirect()->back()
                    ->withErrors(['brackets' => $e->getMessage()])
                    ->withInput();
            }
        }

        try {
            DB::beginTransaction();

            $schedule = StatutorySchedule::create([
                'agency' => $validated['agency'],
                'schedule_version' => $validated['schedule_version'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'pay_frequency' => $validated['pay_frequency'] ?? 'MONTHLY',
                'premium_rate' => $validated['premium_rate'] ?? null,
                'salary_floor' => $validated['salary_floor'] ?? null,
                'salary_ceiling' => $validated['salary_ceiling'] ?? null,
                'compensation_cap' => $validated['compensation_cap'] ?? null,
                'issuance_reference' => $validated['issuance_reference'] ?? null,
                'is_active' => true,
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]);

            $seq = 1;
            foreach ($rawBrackets as $b) {
                StatutoryBracket::create([
                    'statutory_schedule_id' => $schedule->statutory_schedule_id,
                    'bracket_sequence' => $seq++,
                    'range_from' => $b['range_from'],
                    'range_to' => $b['range_to'] !== '' ? $b['range_to'] : null,
                    'employee_share' => $b['employee_share'] !== '' ? $b['employee_share'] : null,
                    'employer_share' => $b['employer_share'] !== '' ? $b['employer_share'] : null,
                    'base_tax' => $b['base_tax'] !== '' ? $b['base_tax'] : null,
                    'marginal_rate' => $b['marginal_rate'] !== '' ? $b['marginal_rate'] : null,
                    'created_by' => $user->user_id,
                    'updated_by' => $user->user_id,
                ]);
            }

            $this->auditService->record(
                $user,
                'STATUTORY_SCHEDULE',
                $schedule->statutory_schedule_id,
                'CREATE',
                null,
                [
                    'agency' => $schedule->agency,
                    'schedule_version' => $schedule->schedule_version,
                    'effective_from' => $schedule->effective_from->format('Y-m-d'),
                ]
            );

            DB::commit();

            return redirect()->route('statutory-schedules.index', ['agency' => $schedule->agency])
                ->with('success', "Statutory schedule '{$schedule->schedule_version}' created successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['schedule' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Show schedule version details and brackets.
     */
    public function show(Request $request, StatutorySchedule $statutorySchedule): View
    {
        $user = $request->user();
        if (! $this->authorizationService->can($user, 'statutory_tables.view') &&
            ! $this->authorizationService->can($user, 'statutory_tables.manage')) {
            abort(403, 'You do not have permission to view statutory schedules.');
        }

        $canManage = $this->authorizationService->can($user, 'statutory_tables.manage');
        $statutorySchedule->load('brackets');

        $isApplied = DeductionLine::query()
            ->where('statutory_schedule_id', $statutorySchedule->statutory_schedule_id)
            ->whereHas('payrollLine.run', function ($q) {
                $q->where('run_status', 'FINALIZED');
            })
            ->exists();

        return view('statutory-schedules.show', [
            'schedule' => $statutorySchedule,
            'canManage' => $canManage,
            'isApplied' => $isApplied,
        ]);
    }

    /**
     * Edit schedule (refused if applied to finalized run per UC-05 E3).
     */
    public function edit(Request $request, StatutorySchedule $statutorySchedule): View|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'statutory_tables.manage');

        $isApplied = DeductionLine::query()
            ->where('statutory_schedule_id', $statutorySchedule->statutory_schedule_id)
            ->whereHas('payrollLine.payrollRun', function ($q) {
                $q->where('run_status', 'FINALIZED');
            })
            ->exists();

        if ($isApplied) {
            return redirect()->route('statutory-schedules.show', $statutorySchedule)
                ->with('error', 'This schedule is locked because it was already applied to a finalized payroll run (UC-05 E3). Create a new dated version instead.');
        }

        $statutorySchedule->load('brackets');

        return view('statutory-schedules.edit', [
            'schedule' => $statutorySchedule,
        ]);
    }

    /**
     * Update unused schedule.
     */
    public function update(Request $request, StatutorySchedule $statutorySchedule): RedirectResponse
    {
        $user = $request->user();
        $this->authorizationService->authorize($user, 'statutory_tables.manage');

        $isApplied = DeductionLine::query()
            ->where('statutory_schedule_id', $statutorySchedule->statutory_schedule_id)
            ->whereHas('payrollLine.payrollRun', function ($q) {
                $q->where('run_status', 'FINALIZED');
            })
            ->exists();

        if ($isApplied) {
            return redirect()->route('statutory-schedules.show', $statutorySchedule)
                ->with('error', 'Cannot edit a statutory schedule applied to a finalized run (UC-05 E3).');
        }

        $validated = $request->validate([
            'schedule_version' => ['required', 'string', 'max:50'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'premium_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'salary_floor' => ['nullable', 'numeric', 'min:0'],
            'salary_ceiling' => ['nullable', 'numeric', 'min:0'],
            'compensation_cap' => ['nullable', 'numeric', 'min:0'],
            'issuance_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $prev = $statutorySchedule->toArray();
        $statutorySchedule->update(array_merge($validated, ['updated_by' => $user->user_id]));

        $this->auditService->record(
            $user,
            'STATUTORY_SCHEDULE',
            $statutorySchedule->statutory_schedule_id,
            'UPDATE',
            $prev,
            $statutorySchedule->fresh()->toArray()
        );

        return redirect()->route('statutory-schedules.show', $statutorySchedule)
            ->with('success', "Schedule '{$statutorySchedule->schedule_version}' updated successfully.");
    }

    /**
     * Set end date to close a superseded schedule (UC-05 A2).
     */
    public function endDate(Request $request, StatutorySchedule $statutorySchedule): RedirectResponse
    {
        $user = $request->user();
        $this->authorizationService->authorize($user, 'statutory_tables.manage');

        $validated = $request->validate([
            'effective_to' => ['required', 'date', 'after:effective_from'],
        ]);

        $prev = ['effective_to' => $statutorySchedule->effective_to?->format('Y-m-d')];
        $statutorySchedule->update([
            'effective_to' => $validated['effective_to'],
            'updated_by' => $user->user_id,
        ]);

        $this->auditService->record(
            $user,
            'STATUTORY_SCHEDULE',
            $statutorySchedule->statutory_schedule_id,
            'UPDATE',
            $prev,
            ['effective_to' => $validated['effective_to']]
        );

        return redirect()->route('statutory-schedules.show', $statutorySchedule)
            ->with('success', "End date set to {$validated['effective_to']} for schedule {$statutorySchedule->schedule_version}.");
    }
}
