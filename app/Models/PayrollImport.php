<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — PAYROLL_IMPORT (added by CR-01, BR-39). One
// row per accepted register version; the superseded-but-retained history
// UC-33 reads. trg_payroll_imports_restricted_update permits only
// is_current (plus bookkeeping columns) to change after insert, and
// trg_payroll_imports_no_delete forbids DELETE — see the
// 2025_08_31_000041_create_business_rule_triggers migration. This model
// never issues an update() outside PayrollImportService's supersession
// step, which touches only is_current.
class PayrollImport extends Model
{
    protected $primaryKey = 'payroll_import_id';

    protected $fillable = [
        'payroll_run_id',
        'import_column_map_id',
        'version_no',
        'source_filename',
        'source_sha256',
        'source_file',
        'imported_by',
        'imported_at',
        'row_count',
        'control_total_gross',
        'control_total_deductions',
        'control_total_net',
        'reconciliation_result',
        'is_current',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'control_total_gross' => 'decimal:2',
            'control_total_deductions' => 'decimal:2',
            'control_total_net' => 'decimal:2',
            'reconciliation_result' => 'array',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * @return BelongsTo<ImportColumnMap, $this>
     */
    public function columnMap(): BelongsTo
    {
        return $this->belongsTo(ImportColumnMap::class, 'import_column_map_id', 'import_column_map_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by', 'user_id');
    }
}
