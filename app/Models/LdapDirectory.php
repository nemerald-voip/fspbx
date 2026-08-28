<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LdapDirectory extends Model
{
    use Traits\TraitUuid;

    protected $table = 'ldap_directories';
    protected $primaryKey = 'directory_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'type',
        'name',
        'enabled',
        'priority',
        'sync_interval_minutes',
        'secure_connection',
        'hosts',
        'port',
        'bind_username',
        'bind_password',
        'ad_domain',
        'base_dn',
        'create_missing_extensions',
        'manage_groups_locally',
        'common_name_attribute',
        'description_attribute',
        'unique_identifier_attribute',
        'user_dn',
        'user_object_class',
        'user_object_filter',
        'user_name_attribute',
        'user_first_name_attribute',
        'user_last_name_attribute',
        'user_display_name_attribute',
        'user_group_attribute',
        'user_email_attribute',
        'user_title_attribute',
        'user_company_attribute',
        'user_department_attribute',
        'user_home_phone_attribute',
        'user_work_phone_attribute',
        'user_cell_phone_attribute',
        'user_fax_attribute',
        'user_extension_attribute',
        'group_dn',
        'group_object_class',
        'group_object_filter',
        'group_members_attribute',
        'connection_status',
        'connection_message',
        'connection_tested_at',
        'last_sync_status',
        'last_sync_message',
        'last_sync_at',
        'next_sync_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'manage_groups_locally' => 'boolean',
        'bind_password' => 'encrypted',
        'connection_tested_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];

    protected $hidden = ['bind_password'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function directoryUsers(): HasMany
    {
        return $this->hasMany(LdapDirectoryUser::class, 'directory_uuid', 'directory_uuid');
    }

    public function directoryGroups(): HasMany
    {
        return $this->hasMany(LdapDirectoryGroup::class, 'directory_uuid', 'directory_uuid');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(LdapSyncRun::class, 'directory_uuid', 'directory_uuid');
    }

    public function hasBindPassword(): bool
    {
        return filled($this->bind_password);
    }

    public function getHasBindPasswordAttribute(): bool
    {
        return $this->hasBindPassword();
    }

    public function userSearchBase(): string
    {
        return $this->relativeBase($this->user_dn);
    }

    public function groupSearchBase(): string
    {
        return $this->relativeBase($this->group_dn);
    }

    private function relativeBase(?string $relativeDn): string
    {
        $relativeDn = trim((string) $relativeDn, " \t\n\r\0\x0B,");

        if ($relativeDn !== '' && str_ends_with(mb_strtolower($relativeDn), mb_strtolower(trim($this->base_dn)))) {
            return $relativeDn;
        }

        return $relativeDn === '' ? $this->base_dn : $relativeDn . ',' . $this->base_dn;
    }
}
