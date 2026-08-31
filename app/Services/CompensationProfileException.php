<?php

namespace App\Services;

use RuntimeException;

// UC-11 E1 — a friendly, field-naming refusal for an overlapping dated
// entry, raised before the write reaches trg_compensation_profiles_no_overlap
// (compensation profiles) or an application-level overlap check (recurring
// earnings/deductions, which carry no DB trigger — see the
// 2025_08_31_000041_create_business_rule_triggers migration's trigger list).
class CompensationProfileException extends RuntimeException {}
