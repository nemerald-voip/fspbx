<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use App\Data\ExtensionListData;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class UserData extends Data
{
    public function __construct(
        public string  $user_uuid,
        public string  $user_email,
        public ?string  $first_name,
        public ?string  $last_name,
        public ?string  $name_formatted,
        public string  $user_enabled,
        public ?string $domain_uuid,
        public ?string $language,
        public ?string $time_zone,
        public ?string $extension_uuid,

        public ?ExtensionListData $extension = null,

        #[DataCollectionOf(UserGroupData::class)]
        public ?DataCollection $user_groups = null,

        #[DataCollectionOf(DomainPermissionData::class)]
        public ?DataCollection $domain_permissions = null,

        #[DataCollectionOf(DomainGroupPermissionData::class)]
        public ?DataCollection $domain_group_permissions = null,

        #[DataCollectionOf(LocationData::class)]
        public ?DataCollection $locations = null,
    ) {}
}
