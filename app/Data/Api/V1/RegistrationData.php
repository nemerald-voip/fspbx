<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class RegistrationData extends Data
{
    public function __construct(
        public string $call_id,
        public string $object,
        public string $domain_uuid,
        public ?string $user = null,
        public ?string $status = null,
        public ?string $lan_ip = null,
        public ?string $port = null,
        public ?string $contact = null,
        public ?string $agent = null,
        public ?string $transport = null,
        public ?string $wan_ip = null,
        public ?string $sip_profile_name = null,
        public ?string $sip_auth_user = null,
        public ?string $sip_auth_realm = null,
        public ?string $ping_time = null,
        public ?string $expsecs = null,
    ) {}
}
