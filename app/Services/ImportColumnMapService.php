<?php

namespace App\Services;

use App\Models\ImportColumnMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// UC-04 mapping editor / FR-2.8 behavior 1 / AD-17 / BR-41.
//
// The canonical field set — which codes exist for earnings, deductions,
// and employer shares — is fixed by FR-2.8's own definition of the
// register's content, and matches ImportColumnMapSeeder's CANONICAL row
// exactly. Only the header STRING bound to each field changes from one
// register layout to the next; that is what AD-17 means by a renamed or
// reordered column, and it is all this class lets an Administrator edit.
class ImportColumnMapService
{
    public const EARNING_CODES = ['BASIC', 'OT', 'NIGHT_DIFF', 'HOLIDAY_PAY', 'ALLOWANCE', 'THIRTEENTH_MONTH'];

    public const DEDUCTION_CODES = ['SSS', 'PHILHEALTH', 'PAGIBIG', 'WTAX', 'LOAN', 'OTHER'];

    public const EMPLOYER_SHARE_CODES = ['SSS', 'PHILHEALTH', 'PAGIBIG'];

    /**
     * BR-41 — a new version's effective_from must fall strictly after the
     * latest existing version's, and that latest version's open-ended
     * effective_to is closed the day before, so no two versions of the
     * same map_name ever have overlapping effective ranges by
     * construction rather than by a refusal an Administrator has to
     * work around.
     *
     * @param  array<string, mixed>  $columnBindings
     */
    public function createVersion(string $mapName, array $columnBindings, string $effectiveFrom, ?int $actorUserId): ImportColumnMap
    {
        $latest = ImportColumnMap::query()->where('map_name', $mapName)->orderByDesc('version_no')->first();

        if ($latest && $effectiveFrom <= $latest->effective_from->toDateString()) {
            throw new ImportColumnMapException(
                "BR-41: the new version's effective date ({$effectiveFrom}) must be after version {$latest->version_no}'s effective date ({$latest->effective_from->toDateString()})."
            );
        }

        return DB::transaction(function () use ($mapName, $columnBindings, $effectiveFrom, $latest, $actorUserId) {
            if ($latest && $latest->effective_to === null) {
                $latest->effective_to = Carbon::parse($effectiveFrom)->subDay()->toDateString();
                $latest->updated_by = $actorUserId;
                $latest->save();
            }

            return ImportColumnMap::create([
                'map_name' => $mapName,
                'version_no' => $latest ? $latest->version_no + 1 : 1,
                'column_bindings' => $columnBindings,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
        });
    }
}
