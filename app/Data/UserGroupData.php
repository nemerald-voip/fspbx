<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UserGroupData extends Data
{
    public function __construct(
        public string $user_group_uuid,
        public string $group_uuid,
        public string $group_name,
    ) {}
}

