<?php

namespace App\Data\Api\V1;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserData extends Data
{
    public function __construct(
        public string $user_uuid,
        public string $object,
        public string $domain_uuid,
        public string $username,
        public ?string $user_email,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $name_formatted,
        public bool $user_enabled,
        public bool $is_domain_admin,
        public string|Optional|null $language = new Optional(),
        public string|Optional|null $time_zone = new Optional(),
        public ?string $created_at = null,
    ) {}

    public static function fromModel(User $user): self
    {
        $isAdmin = false;
        if ($user->relationLoaded('user_groups') || $user->user_groups) {
            $isAdmin = $user->user_groups->contains(function ($ug) {
                return strtolower((string) $ug->group_name) === 'admin'
                    || strtolower((string) $ug->group_name) === 'superadmin';
            });
        }

        return new self(
            user_uuid: (string) $user->user_uuid,
            object: 'user',
            domain_uuid: (string) $user->domain_uuid,
            username: (string) $user->username,
            user_email: $user->user_email,
            first_name: $user->first_name ?: null,
            last_name: $user->last_name ?: null,
            name_formatted: $user->name_formatted,
            user_enabled: self::textBool($user->user_enabled),
            is_domain_admin: $isAdmin,
            language: $user->language,
            time_zone: $user->time_zone,
            created_at: $user->add_date ? (string) $user->add_date : null,
        );
    }

    private static function textBool($value): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return false;
        $v = strtolower(trim((string) $value));
        return in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true);
    }
}
