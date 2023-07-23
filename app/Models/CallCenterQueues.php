<?php

namespace App\Models;

use App\Models\CallCenterAgents;
use App\Models\CallCenterQueueAgents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallCenterQueues extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_call_center_queues";

    public $timestamps = false;

    protected $primaryKey = 'call_center_queue_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'queue_name',
        'queue_extension',
        'queue_greeting',
        'queue_moh_sound',
        'queue_strategy',
        'queue_record_template',
        'queue_time_base_score',
        'queue_time_base_score_sec',
        'queue_max_wait_time',
        'queue_max_wait_time_with_no_agent',
        'queue_max_wait_time_with_no_agent_time_reached',
        'queue_tier_rules_apply',
        'queue_tier_rule_wait_second',
        'queue_tier_rule_no_agent_no_wait',
        'queue_timeout_action',
        'queue_discard_abandoned_after',
        'queue_abandoned_resume_allowed',
        'queue_tier_rule_wait_multiply_level',
        'queue_cid_prefix',
        'queue_outbound_caller_id_name',
        'queue_outbound_caller_id_number',
        'queue_announce_sound',
        'queue_announce_frequency',
        'queue_cc_exit_keys',
        'queue_description',
        'queue_announce_position',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
        'queue_email_address'
    ];

    public function agents()
    {
        return $this->belongsToMany(CallCenterAgents::class, CallCenterQueueAgents::class, 'call_center_queue_uuid', 'call_center_agent_uuid');
    }
}
