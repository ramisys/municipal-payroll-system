<?php

namespace App\Services;

use Exception;

// Internal control-flow exception for AttendanceImportService::validateRow —
// caught inside preview() to turn one bad row (E2-E4) into a rejected-row
// entry without aborting evaluation of the remaining rows. Never escapes
// the service; AttendanceImportException is the public refusal type.
class AttendanceRowRejected extends Exception {}
