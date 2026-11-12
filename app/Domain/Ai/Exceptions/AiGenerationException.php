<?php

namespace App\Domain\Ai\Exceptions;

use RuntimeException;
use Throwable;

final class AiGenerationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $safeMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, previous: $previous);
    }
}
