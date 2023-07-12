<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class RingGroups extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_ring_groups";

    public $timestamps = false;

    protected $primaryKey = 'ring_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ring_group_extension',
        'ring_group_greeting',
        'ring_group_strategy',
        'ring_group_name',
        'ring_group_call_timeout',
        'ring_group_timeout_app',
        'ring_group_timeout_data',
        'ring_group_cid_name_prefix',
        'ring_group_cid_number_prefix',
        'ring_group_description',
        'ring_group_enabled',
        'ring_group_forward_enabled',
        'ring_group_strategy',
        'ring_group_caller_id_name',
        'ring_group_caller_id_number',
        'ring_group_distinctive_ring',
        'ring_group_ringback',
        'ring_group_call_forward_enabled',
        'ring_group_follow_me_enabled',
        'ring_group_missed_call_data',
        'ring_group_forward_toll_allow',
        'ring_group_forward_context'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->attributes['ring_group_context'] = Session::get('domain_name');
        $this->attributes['ring_group_enabled'] = "true";
        $this->attributes['ring_group_strategy'] = "enterprise";
        $this->attributes['ring_group_call_timeout'] = "30";
        $this->attributes['ring_group_ringback'] = '${us-ring}';
        $this->attributes['ring_group_call_forward_enabled'] = "true";
        $this->attributes['ring_group_follow_me_enabled'] = "true";

        $this->fill($attributes);
    }

    public function getId()
    {
        return $this->ring_group_extension;
    }

    public function getName()
    {
        return $this->ring_group_extension.' - '.$this->ring_group_name;
    }

    public function getGroupDestinations()
    {
        return $this->belongsTo(RingGroupsDestinations::class,'ring_group_uuid','ring_group_uuid')->get();
    }

    public function groupDestinations()
    {
        return $this->hasMany(RingGroupsDestinations::class,'ring_group_uuid','ring_group_uuid');
    }
}
