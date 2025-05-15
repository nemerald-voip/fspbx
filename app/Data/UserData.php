<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class UserData extends Data
{
    public function __construct(
        public string  $user_uuid,
        public string  $user_email,
        public string  $name_formatted,
        public bool    $user_enabled,
        public ?string $domain_uuid,
        public ?string $language,
        public ?string $time_zone,
        
        #[DataCollectionOf(UserGroupData::class)]
        public ?DataCollection $user_groups = null,
    ) {}
}
