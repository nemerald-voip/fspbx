<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class RingGroupData extends Data
{
    public function __construct(
        public string $ring_group_uuid,
        public string $object,
        public string $domain_uuid,

        public string $ring_group_name,
        public string $ring_group_extension,

        public string|Optional|null $ring_group_greeting = new Optional(),

        public string|Optional|null $ring_group_caller_id_name = new Optional(),
        public string|Optional|null $ring_group_caller_id_number = new Optional(),

        public string|Optional|null $ring_group_cid_name_prefix = new Optional(),
        public string|Optional|null $ring_group_cid_number_prefix = new Optional(),

        public string|Optional|null $ring_group_strategy = new Optional(),

        public string|Optional|null $timeout_action = new Optional(),
        public string|Optional|null $timeout_target = new Optional(),

        public bool|Optional|null $ring_group_call_forward_enabled = new Optional(),
        public bool|Optional|null $ring_group_follow_me_enabled = new Optional(),

        public ?string $ring_group_description = null,

        public bool|Optional|null $ring_group_forward_enabled = new Optional(),
        public string|Optional|null $forward_action = new Optional(),
        public string|Optional|null $forward_target = new Optional(),

        /** @var array<int, RingGroupDestinationData>|null */
        public array|Optional|null $members = new Optional(),
    ) {}
}
