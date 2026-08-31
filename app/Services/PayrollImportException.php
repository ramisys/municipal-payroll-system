<?php

namespace App\Services;

use RuntimeException;

// UC-18 refusals that are neither RegisterParseException's structural E1
// nor ReconciliationException's E2-E5: the run itself is not in an
// editable state (E9), or — a schema consequence rather than a named
// exception flow — a matched active employee has no compensation profile
// in force on the period's cutoff end date. PAYROLL_LINE.compensation_
// profile_id has no NULL path (2025_08_31_000033 migration), and EX-02's
// full exception-rule treatment (UC-I4) traces to FR-4.1, which is out of
// this slice (pre-oral-demonstration-plan.md §3.3) — so this refusal
// blocks the whole import rather than writing a line the schema cannot
// hold, and is reported by employee number like every other refusal here.
class PayrollImportException extends RuntimeException {}
