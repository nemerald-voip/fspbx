<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ActiveCallData extends Data
{
    public function __construct(
        public string $uuid,
        public string $object,
        public string $domain_uuid,
        public ?string $direction = null,
        public ?string $created = null,
        public ?string $created_epoch = null,
        public ?string $created_display = null,
        public ?int $start_epoch = null,
        public ?int $duration_seconds = null,
        public ?string $name = null,
        public ?string $state = null,
        public ?string $cid_name = null,
        public ?string $cid_num = null,
        public ?string $ip_addr = null,
        public ?string $dest = null,
        public ?string $application = null,
        public ?string $application_data = null,
        public ?string $app_full = null,
        public ?string $app_preview = null,
        public ?string $dialplan = null,
        public ?string $context = null,
        public ?string $read_codec = null,
        public ?string $read_rate = null,
        public ?string $read_bit_rate = null,
        public ?string $write_codec = null,
        public ?string $write_rate = null,
        public ?string $write_bit_rate = null,
        public ?string $secure = null,
        public ?string $hostname = null,
        public ?string $presence_id = null,
        public ?string $presence_data = null,
        public ?string $accountcode = null,
        public ?string $callstate = null,
        public ?string $callee_name = null,
        public ?string $callee_num = null,
        public ?string $callee_direction = null,
        public ?string $call_uuid = null,
        public ?string $sent_callee_name = null,
        public ?string $sent_callee_num = null,
        public ?string $initial_cid_name = null,
        public ?string $initial_cid_num = null,
        public ?string $initial_ip_addr = null,
        public ?string $initial_dest = null,
        public ?string $initial_dialplan = null,
        public ?string $initial_context = null,
        public ?string $display_timezone = null,
    ) {}
}
