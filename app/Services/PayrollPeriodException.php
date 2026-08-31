<?php

namespace App\Services;

use RuntimeException;

// UC-03 E1 (BR-34 overlap/gap) and E2 (period already used by a run) —
// both refusals PayrollPeriodGenerationService raises before any invalid
// calendar state is persisted.
class PayrollPeriodException extends RuntimeException {}
