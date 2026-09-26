<?php

namespace App\Domains\AI\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(string $message, private readonly bool $retryable = true)
    {
        parent::__construct($message);
    }

    /**
     * Retryable errors (429/5xx/timeouts/invalid output) let the gateway try
     * the next provider; permanent request errors would fail identically, so
     * the chain stops instead of burning time and budget.
     */
    public function retryable(): bool
    {
        return $this->retryable;
    }
}
