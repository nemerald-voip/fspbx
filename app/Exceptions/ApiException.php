<?php

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        public int $status,
        public string $type,          // e.g. invalid_request_error, authentication_error
        string $message,
        public ?string $error_code = null,  // e.g. parameter_missing, forbidden, resource_missing
        public ?string $param = null, // e.g. "domain_name"
        public array $extra = [],     // optional extra fields if you want them
    ) {
        parent::__construct($message);
    }
}
