<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use RuntimeException;

/**
 * LLM provider failure. $errorCode ∈ provider_timeout | provider_error | quota_exceeded.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'provider_error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
