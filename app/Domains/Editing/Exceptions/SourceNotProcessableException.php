<?php

namespace App\Domains\Editing\Exceptions;

use RuntimeException;

/**
 * The edit source exists but cannot be processed (no completed image
 * output, missing file, non-image media, ...). Maps to 422 at the API and
 * to a permanent task failure inside the job.
 */
class SourceNotProcessableException extends RuntimeException {}
