<?php

namespace App\Domains\Editing\Exceptions;

use RuntimeException;

/**
 * The edit source does not exist or belongs to another user: the request
 * must answer 404 and the job must fail without retrying meaningful work.
 */
class SourceNotFoundException extends RuntimeException {}
