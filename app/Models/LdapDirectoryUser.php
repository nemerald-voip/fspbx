<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LdapDirectoryUser extends Model
{
    use Traits\TraitUuid;

    protected $table = 'ldap_directory_users';
    protected $primaryKey = 'directory_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'directory_uuid', 'domain_uuid', 'user_uuid', 'external_id',
        'distinguished_name', 'username', 'email', 'first_name', 'last_name',
        'display_name', 'extension', 'remote_enabled', 'profile', 'last_seen_at',
    ];

    protected $casts = [
        'remote_enabled' => 'boolean',
        'profile' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function directory(): BelongsTo
    {
        return $this->belongsTo(LdapDirectory::class, 'directory_uuid', 'directory_uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'user_uuid');
    }

    public function directoryGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            LdapDirectoryGroup::class,
            'ldap_directory_group_members',
            'directory_user_uuid',
            'directory_group_uuid'
        )->withTimestamps();
    }
}
