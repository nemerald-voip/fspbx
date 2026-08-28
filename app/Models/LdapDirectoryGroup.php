<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LdapDirectoryGroup extends Model
{
    use Traits\TraitUuid;

    protected $table = 'ldap_directory_groups';
    protected $primaryKey = 'directory_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'directory_uuid', 'domain_uuid', 'external_id', 'distinguished_name',
        'name', 'description', 'primary_group_token', 'local', 'last_seen_at',
    ];

    protected $casts = [
        'local' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function directory(): BelongsTo
    {
        return $this->belongsTo(LdapDirectory::class, 'directory_uuid', 'directory_uuid');
    }

    public function directoryUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            LdapDirectoryUser::class,
            'ldap_directory_group_members',
            'directory_group_uuid',
            'directory_user_uuid'
        )->withTimestamps();
    }

    public function localGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            Groups::class,
            'ldap_directory_group_mappings',
            'directory_group_uuid',
            'group_uuid'
        )->withTimestamps();
    }
}
