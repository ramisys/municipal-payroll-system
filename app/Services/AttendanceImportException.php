<?php

namespace App\Services;

use RuntimeException;

// UC-13 E1 — a structural refusal of the whole file (unreadable, no header
// row, a required reference row missing) raised before any row is
// evaluated. A single bad *row* is not this — that is a rejected row in
// the preview (E2-E4), and the import still evaluates the rest.
class AttendanceImportException extends RuntimeException {}
