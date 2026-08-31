<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\OrganizationProfile;
use App\Models\PayrollPeriod;
use App\Models\SystemConfig;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayrollPeriodException;
use App\Services\PayrollPeriodGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// UC-03 · Configure organization profile and payroll calendar — FR-0.3,
// BR-34. Administrator only ('organization.manage'), following the same
// explicit-authorize-at-every-action convention as UserController.
//
// Three sub-screens share this controller because they share one use
// case and one Administrator "system configuration" landing point (UC-03
// main flow step 1): the employer profile, the pay-period calendar
// (generated and adjusted through PayrollPeriodGenerationService), and
// the holiday calendar (FR-0.3 behavior 3).
class OrganizationProfileController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly PayrollPeriodGenerationService $periodService,
    ) {}

    // --- Organization profile (FR-0.3 behaviors 1-2) ---------------------

    public function edit(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $profile = OrganizationProfile::query()->first() ?? new OrganizationProfile;

        return view('organization.edit', [
            'profile' => $profile,
            'standardHoursPerDay' => SystemConfig::value('STANDARD_HOURS_PER_DAY', '8.00'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $data = $request->validate([
            'registered_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'sss_employer_no' => ['nullable', 'string', 'max:255'],
            'philhealth_employer_no' => ['nullable', 'string', 'max:255'],
            'pagibig_employer_no' => ['nullable', 'string', 'max:255'],
            'bir_tin' => ['nullable', 'string', 'max:255'],
            'standard_hours_per_day' => ['required', 'numeric', 'min:0'],
        ]);

        $profile = OrganizationProfile::query()->first() ?? new OrganizationProfile;
        $isNew = ! $profile->exists;
        $previousValues = $isNew ? null : $profile->only([
            'registered_name', 'address', 'sss_employer_no', 'philhealth_employer_no', 'pagibig_employer_no', 'bir_tin',
        ]);

        $profile->fill([
            'registered_name' => $data['registered_name'],
            'address' => $data['address'] ?? null,
            'sss_employer_no' => $data['sss_employer_no'] ?? null,
            'philhealth_employer_no' => $data['philhealth_employer_no'] ?? null,
            'pagibig_employer_no' => $data['pagibig_employer_no'] ?? null,
            'bir_tin' => $data['bir_tin'] ?? null,
        ]);
        $profile->updated_by = $request->user()->user_id;
        if ($isNew) {
            $profile->created_by = $request->user()->user_id;
        }
        $profile->save();

        // AC-0.3.3 evidence: STANDARD_HOURS_PER_DAY is SYSTEM_CONFIG data
        // (data-model.md §5.4), not an ORGANIZATION_PROFILE column — set
        // here because UC-03 step 3 presents it on the same screen.
        $config = SystemConfig::query()->where('config_key', 'STANDARD_HOURS_PER_DAY')->first();
        if ($config) {
            $config->config_value = $data['standard_hours_per_day'];
            $config->updated_by = $request->user()->user_id;
            $config->save();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'ORGANIZATION_PROFILE',
            entityId: $profile->organization_id,
            action: $isNew ? 'CREATE' : 'UPDATE',
            previousValues: $previousValues,
            newValues: $profile->only(['registered_name', 'address', 'sss_employer_no', 'philhealth_employer_no', 'pagibig_employer_no', 'bir_tin']),
        );

        return redirect()->route('organization.edit')->with('status', 'Organization profile saved.');
    }

    // --- Payroll calendar (FR-0.3 behaviors 2-3, BR-34) -------------------

    public function periodsIndex(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $periods = PayrollPeriod::query()
            ->orderByDesc('payroll_year')
            ->orderBy('period_no')
            ->get()
            ->groupBy('payroll_year');

        return view('organization.periods', ['periodsByYear' => $periods]);
    }

    public function periodsStore(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $data = $request->validate([
            'payroll_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'pay_frequency' => ['required', Rule::in(['MONTHLY', 'SEMI_MONTHLY'])],
            'pay_date_offset_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        try {
            $periods = $this->periodService->generateForYear(
                $data['payroll_year'],
                $data['pay_frequency'],
                $data['pay_date_offset_days'],
                $request->user()->user_id,
            );
        } catch (PayrollPeriodException $e) {
            return back()->withErrors(['payroll_year' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_PERIOD',
            entityId: null,
            action: 'CREATE',
            newValues: ['payroll_year' => $data['payroll_year'], 'pay_frequency' => $data['pay_frequency'], 'period_count' => count($periods)],
        );

        return redirect()->route('organization.periods.index')->with('status', "Generated {$data['payroll_year']}: ".count($periods).' periods, no overlap and no gap (BR-34).');
    }

    public function periodsEdit(Request $request, PayrollPeriod $period): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        return view('organization.period-edit', ['period' => $period]);
    }

    public function periodsUpdate(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $data = $request->validate([
            'cutoff_start' => ['required', 'date'],
            'cutoff_end' => ['required', 'date', 'after:cutoff_start'],
            'pay_date' => ['required', 'date', 'after_or_equal:cutoff_end'],
        ]);

        $previousValues = $period->only(['cutoff_start', 'cutoff_end', 'pay_date']);

        try {
            $this->periodService->adjustPeriod(
                $period,
                $data['cutoff_start'],
                $data['cutoff_end'],
                $data['pay_date'],
                $request->user()->user_id,
            );
        } catch (PayrollPeriodException $e) {
            return back()->withErrors(['cutoff_start' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_PERIOD',
            entityId: $period->payroll_period_id,
            action: 'UPDATE',
            previousValues: $previousValues,
            newValues: $data,
        );

        return redirect()->route('organization.periods.index')->with('status', 'Period updated. The payroll year still has no overlap and no gap (BR-34).');
    }

    // --- Holiday calendar (FR-0.3 behavior 3) -----------------------------

    public function holidaysIndex(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $holidays = Holiday::query()->orderBy('holiday_date')->get();

        return view('organization.holidays.index', ['holidays' => $holidays]);
    }

    public function holidaysCreate(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        return view('organization.holidays.create');
    }

    public function holidaysStore(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $data = $this->validateHoliday($request);

        $holiday = Holiday::create([
            ...$data,
            'created_by' => $request->user()->user_id,
            'updated_by' => $request->user()->user_id,
        ]);

        $this->auditService->record($request->user(), 'HOLIDAY', $holiday->holiday_id, 'CREATE', null, $data);

        return redirect()->route('organization.holidays.index')->with('status', "'{$holiday->holiday_name}' added to the holiday calendar.");
    }

    public function holidaysEdit(Request $request, Holiday $holiday): View
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        return view('organization.holidays.edit', ['holiday' => $holiday]);
    }

    public function holidaysUpdate(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'organization.manage');

        $data = $this->validateHoliday($request, $holiday->holiday_id);
        $previousValues = $holiday->only(['holiday_date', 'holiday_name', 'holiday_type', 'is_local']);

        $holiday->fill($data);
        $holiday->updated_by = $request->user()->user_id;
        $holiday->save();

        $this->auditService->record($request->user(), 'HOLIDAY', $holiday->holiday_id, 'UPDATE', $previousValues, $data);

        return redirect()->route('organization.holidays.index')->with('status', "'{$holiday->holiday_name}' updated.");
    }

    /**
     * FR-0.3 behavior 3: a date is a regular holiday, a (national) special
     * non-working day, or a local holiday. This schema holds that as
     * holiday_type IN ('REGULAR','SPECIAL_NON_WORKING') plus an is_local
     * flag that only applies when holiday_type is SPECIAL_NON_WORKING —
     * a local holiday is a special-non-working day declared locally
     * rather than nationally; there is no separate "regular local
     * holiday" case in Philippine holiday proclamations.
     *
     * @return array{holiday_date: string, holiday_name: string, holiday_type: string, is_local: bool}
     */
    private function validateHoliday(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($ignoreId, 'holiday_id')],
            'holiday_name' => ['required', 'string', 'max:255'],
            'holiday_type' => ['required', Rule::in(['REGULAR', 'SPECIAL_NON_WORKING'])],
            'is_local' => ['sometimes', 'boolean'],
        ]);

        $data['is_local'] = $request->boolean('is_local') && $data['holiday_type'] === 'SPECIAL_NON_WORKING';

        return $data;
    }
}
