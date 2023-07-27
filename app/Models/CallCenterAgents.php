<?php

namespace App\Models;

use App\Models\CallCenterQueues;
use App\Models\CallCenterQueueAgents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallCenterAgents extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_call_center_agents";

    public $timestamps = false;

    protected $primaryKey = 'call_center_agent_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'call_center_agent_uuid',
        'domain_uuid',
        'user_uuid',
        'agent_name',
        'agent_type',
        'agent_call_timeout',
        'agent_id',
        'agent_password',
        'agent_contact',
        'agent_status',
        'agent_logout',
        'agent_max_no_answer',
        'agent_wrap_up_time',
        'agent_reject_delay_time',
        'agent_busy_delay_time',
        'agent_no_answer_delay_time',
        'agent_record',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

    public function queues()
    {
        return $this->belongsToMany(CallCenterQueues::class, CallCenterQueueAgents::class, 'call_center_agent_uuid', 'call_center_queue_uuid');
    }

    public function queue()
    {
        return $this->belongsTo(CallCenterQueues::class, 'call_center_queue_uuid', 'call_center_queue_uuid');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function tier()
    {
        return $this->belongsTo(CallCenterQueueAgents::class, 'call_center_agent_uuid', 'call_center_agent_uuid');
    }
}
