<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }

    public function isNotFound(): bool
    {
        if ($this->statusCode === 404) {
            return true;
        }

        $message = strtolower($this->getMessage());

        return $this->statusCode === 422
            && (str_contains($message, 'not found') || str_contains($message, 'does not exist'));
    }

    public function isConflict(): bool
    {
        return $this->statusCode === 409;
    }
}
