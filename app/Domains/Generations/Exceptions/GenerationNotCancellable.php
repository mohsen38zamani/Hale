<?php

namespace App\Domains\Generations\Exceptions;

use RuntimeException;

class GenerationNotCancellable extends RuntimeException
{
    public function __construct(string $message = 'این تولید قابل لغو نیست.')
    {
        parent::__construct($message);
    }
}
