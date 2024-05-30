<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Session;

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

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->attributes['queue_strategy'] = 'ring-all';
        $this->attributes['queue_record_template'] = true;
        $this->attributes['queue_time_base_score'] = 'system';
        $this->attributes['queue_max_wait_time_with_no_agent_time_reached'] = '5';
        $this->attributes['queue_tier_rules_apply'] = false;
        $this->attributes['queue_tier_rule_wait_second'] = 30;
        $this->attributes['queue_tier_rule_no_agent_no_wait'] = false;
        $this->attributes['queue_discard_abandoned_after'] = 900;
        $this->attributes['queue_abandoned_resume_allowed'] = false;
        $this->attributes['queue_tier_rule_wait_multiply_level'] = false;
        $this->attributes['queue_greeting'] = '';
        $this->attributes['queue_max_wait_time'] = 0;
        $this->attributes['queue_max_wait_time_with_no_agent'] = 90;
        $this->attributes['queue_moh_sound'] = 'local_stream://default';
        $this->fill($attributes);
    }

    public function agents()
    {
        return $this->belongsToMany(CallCenterAgents::class, CallCenterQueueAgents::class, 'call_center_queue_uuid', 'call_center_agent_uuid');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan()
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }
}
