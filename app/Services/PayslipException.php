<?php

namespace App\Services;

// UC-27 E1 / UC-28 E1 — payslip generation and reprint refusals.
// FR-3.1, FR-3.4. The string carries the run's current state so the
// controller can echo it verbatim ("the system states the run's current
// state" — UC-28 E1).
class PayslipException extends \RuntimeException {}
