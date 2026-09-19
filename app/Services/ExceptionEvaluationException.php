<?php

namespace App\Services;

// Raised by ExceptionEvaluator::acknowledge for invalid acknowledgment
// attempts (blocking rule, already resolved, empty reason).
class ExceptionEvaluationException extends \RuntimeException {}
