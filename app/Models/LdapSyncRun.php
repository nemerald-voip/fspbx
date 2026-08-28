<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdapSyncRun extends Model
{
    use Traits\TraitUuid;

    protected $table = 'ldap_sync_runs';
    protected $primaryKey = 'sync_run_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'directory_uuid', 'domain_uuid', 'status', 'dry_run', 'users_seen',
        'users_created', 'users_updated', 'users_disabled', 'users_conflicted',
        'groups_seen', 'messages', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'messages' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
