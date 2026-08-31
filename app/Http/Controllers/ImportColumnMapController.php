<?php

namespace App\Http\Controllers;

use App\Models\ImportColumnMap;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\ImportColumnMapException;
use App\Services\ImportColumnMapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC-04 mapping editor (pre-oral-demonstration-plan.md §3.2, UC-04's "why
// in the slice" row) / FR-2.8 behavior 1, AD-17, BR-41, C-01.
// Administrator only ('import_column_map.manage').
//
// AD-17's whole premise is that a *renamed or reordered* register column
// is absorbed by publishing a new IMPORT_COLUMN_MAP version rather than
// changing RegisterImportService's source — this screen is that
// evidence: a Administrator can retarget every canonical field to a new
// header string without a code change, and the previous version is kept,
// never overwritten (data-model.md §5.2 "no UPDATE except is_active").
//
// Scoped to the single 'CANONICAL' map_name this release ships
// (ImportColumnMapSeeder) — a second register source would be a second
// map_name, not built here since only one is in scope of the pre-oral
// slice (OI-12).
class ImportColumnMapController extends Controller
{
    private const MAP_NAME = 'CANONICAL';

    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly ImportColumnMapService $mapService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'import_column_map.manage');

        $versions = ImportColumnMap::query()
            ->where('map_name', self::MAP_NAME)
            ->orderByDesc('version_no')
            ->get();

        return view('import-column-maps.index', ['versions' => $versions]);
    }

    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'import_column_map.manage');

        $latest = ImportColumnMap::query()->where('map_name', self::MAP_NAME)->orderByDesc('version_no')->first();

        return view('import-column-maps.create', [
            'bindings' => $latest?->column_bindings,
            'earningCodes' => ImportColumnMapService::EARNING_CODES,
            'deductionCodes' => ImportColumnMapService::DEDUCTION_CODES,
            'employerShareCodes' => ImportColumnMapService::EMPLOYER_SHARE_CODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'import_column_map.manage');

        $rules = [
            'employee_no' => ['required', 'string', 'max:255'],
            'gross_pay' => ['required', 'string', 'max:255'],
            'total_deductions' => ['required', 'string', 'max:255'],
            'net_pay' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
        ];

        foreach (ImportColumnMapService::EARNING_CODES as $code) {
            $rules["earnings.{$code}"] = ['required', 'string', 'max:255'];
        }
        foreach (ImportColumnMapService::DEDUCTION_CODES as $code) {
            $rules["deductions.{$code}"] = ['required', 'string', 'max:255'];
        }
        foreach (ImportColumnMapService::EMPLOYER_SHARE_CODES as $code) {
            $rules["employer_shares.{$code}"] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $bindings = [
            'employee_no' => $data['employee_no'],
            'earnings' => $data['earnings'],
            'deductions' => $data['deductions'],
            'employer_shares' => $data['employer_shares'],
            'gross_pay' => $data['gross_pay'],
            'total_deductions' => $data['total_deductions'],
            'net_pay' => $data['net_pay'],
        ];

        try {
            $version = $this->mapService->createVersion(self::MAP_NAME, $bindings, $data['effective_from'], $request->user()->user_id);
        } catch (ImportColumnMapException $e) {
            return back()->withErrors(['effective_from' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'IMPORT_COLUMN_MAP',
            entityId: $version->import_column_map_id,
            action: 'CREATE',
            newValues: ['map_name' => self::MAP_NAME, 'version_no' => $version->version_no, 'effective_from' => $data['effective_from']],
        );

        return redirect()->route('import-column-maps.index')->with('status', "Version {$version->version_no} published. A renamed or reordered column is now a configuration change, not a code change (AD-17).");
    }

    public function deactivate(Request $request, ImportColumnMap $importColumnMap): RedirectResponse
    {
        return $this->setActive($request, $importColumnMap, false);
    }

    public function reactivate(Request $request, ImportColumnMap $importColumnMap): RedirectResponse
    {
        return $this->setActive($request, $importColumnMap, true);
    }

    private function setActive(Request $request, ImportColumnMap $importColumnMap, bool $active): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'import_column_map.manage');

        $importColumnMap->is_active = $active;
        $importColumnMap->updated_by = $request->user()->user_id;
        $importColumnMap->save();

        $this->auditService->record(
            $request->user(),
            'IMPORT_COLUMN_MAP',
            $importColumnMap->import_column_map_id,
            'UPDATE',
            ['is_active' => ! $active],
            ['is_active' => $active],
        );

        return redirect()->route('import-column-maps.index')->with('status', $active ? 'Version reactivated.' : 'Version retired.');
    }
}
