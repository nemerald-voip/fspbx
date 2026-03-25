<?php

namespace App\Services\Messaging\Outbound\Data;

use Spatie\LaravelData\Data;

class OutboundSendResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $status, // success | failed | queued
        public ?string $providerReferenceId = null,
        public ?string $error = null,
        public array $providerResponse = [],
    ) {}
}