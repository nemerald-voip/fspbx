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
        'groups_seen', 'messages', 'started_at', 'finished_at', 'node_name',
        'scheduled_job_execution_uuid', 'node_id', 'ownership_generation',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'messages' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'ownership_generation' => 'integer',
    ];

    public function execution()
    {
        return $this->belongsTo(ScheduledJobExecution::class, 'scheduled_job_execution_uuid', 'scheduled_job_execution_uuid');
    }
}
