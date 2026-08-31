<?php

namespace App\Services;

use RuntimeException;

// BR-41 refusal: a new IMPORT_COLUMN_MAP version whose effective_from
// would overlap an existing version for the same map_name.
class ImportColumnMapException extends RuntimeException {}
