<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallCenterQueueAgents extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_call_center_tiers";

    public $timestamps = false;

    protected $primaryKey = 'call_center_tier_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'call_center_tier_uuid',
        'domain_uuid',
        'call_center_queue_uuid',
        'call_center_agent_uuid',
        'agent_name',
        'queue_name',
        'tier_level',
        'tier_position',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

}
