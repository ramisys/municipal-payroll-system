<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.1 / §5.1 — ORGANIZATION_PROFILE. A singleton row: the
// system serves one employer (system-architecture.md §2). `logo` is a
// MEDIUMBLOB the migration adds outside Blueprint (see the migration).
class OrganizationProfile extends Model
{
    protected $primaryKey = 'organization_id';

    protected $fillable = [
        'registered_name',
        'address',
        'sss_employer_no',
        'philhealth_employer_no',
        'pagibig_employer_no',
        'bir_tin',
        'logo',
        'created_by',
        'updated_by',
    ];
}
