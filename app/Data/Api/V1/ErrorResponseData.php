<?php 
namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ErrorData extends Data
{
    public function __construct(
        public string $type,          // invalid_request_error | authentication_error | ...
        public string $message,
        public ?string $code = null,  // parameter_missing, forbidden, ...
        public ?string $param = null,
        public ?string $doc_url = null,
    ) {}
}

class ErrorResponseData extends Data
{
    public function __construct(public ErrorData $error) {}
}
