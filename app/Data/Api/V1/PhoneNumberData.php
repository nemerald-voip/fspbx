<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PhoneNumberData extends Data
{
    /**
     * @param array<int, \App\Data\Api\V1\PhoneNumberActionData>|Optional|null $destination_actions
     */
    public function __construct(
        // --- identity ---
        public string $destination_uuid,
        public string $object,
        public string $domain_uuid,

        // --- common/core fields ---
        public string|Optional|null $destination_number = new Optional(),
        public string|Optional|null $destination_prefix = new Optional(),

        public bool|Optional|null $destination_enabled = new Optional(),
        public string|Optional|null $destination_description = new Optional(),

        public string|Optional|null $fax_uuid = new Optional(),

        public bool|Optional|null $destination_record = new Optional(),
        public bool|Optional|null $destination_type_fax = new Optional(),

        public string|Optional|null $destination_hold_music = new Optional(),
        public string|Optional|null $destination_distinctive_ring = new Optional(),
        public string|Optional|null $destination_cid_name_prefix = new Optional(),
        public string|Optional|null $destination_accountcode = new Optional(),

        // --- computed by model accessor ---
        /** @var array<int, mixed>|Optional|null */
        public array|Optional|null $routing_options = new Optional(),
    ) {}
}
