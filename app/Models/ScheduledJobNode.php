<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledJobNode extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'scheduled_job_node_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'system_identifier', 'hostname', 'endpoint', 'status', 'approved_at',
        'approved_by', 'retired_at', 'retired_by', 'host_fingerprint', 'registered_on_node_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'retired_at' => 'datetime',
    ];
}
