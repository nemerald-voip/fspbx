<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledJobHandoff extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'scheduled_job_handoff_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'idempotency_key', 'from_node_id', 'to_node_id', 'expected_generation',
        'status', 'forced', 'fenced_endpoint', 'requested_by', 'forced_by', 'message', 'requested_at', 'completed_at',
    ];

    protected $casts = [
        'forced' => 'boolean',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_generation' => 'integer',
    ];
}
