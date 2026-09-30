<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserData extends Data
{
    public function __construct(
        public string $user_uuid,
        public string $object,
        public string $domain_uuid,
        public ?string $user_email,
        public string $first_name,
        public string $last_name,
        public bool $user_enabled,
        public ?string $extension_uuid,
        /** @var string[] */
        public array $groups,
        public bool $directory_managed,
        public string|Optional|null $time_zone = new Optional(),
        /** @var string[]|Optional */
        public array|Optional $accounts = new Optional(),
        /** @var string[]|Optional */
        public array|Optional $account_groups = new Optional(),
        /** @var string[]|Optional */
        public array|Optional $locations = new Optional(),
    ) {}
}
