<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledJobExecution extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'scheduled_job_execution_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'job_type', 'job_key', 'node_id', 'ownership_generation', 'status',
        'started_at', 'expires_at', 'finished_at', 'message',
    ];

    protected $casts = [
        'ownership_generation' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
