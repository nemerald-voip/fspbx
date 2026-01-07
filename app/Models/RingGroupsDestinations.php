<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class RingGroupsDestinations extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_ring_group_destinations";

    public $timestamps = false;

    protected $primaryKey = 'ring_group_destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ring_group_destination_uuid',
        'domain_uuid',
        'ring_group_uuid',
        'destination_number',
        'destination_delay',
        'destination_timeout',
        'destination_prompt',
        'destination_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->attributes['update_date'] = date('Y-m-d H:i:s');
        $this->attributes['update_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }

    public function ringGroups()
    {
        return $this->hasOne(RingGroups::class, 'ring_group_uuid', 'ring_group_uuid');
    }

    /**
     * This gets you “all possible” extensions where destination_number matches extension, regardless of domain.
     * Further filtering by domain is REQUIRED to avoid false positives and PERFORMANCE ISSUES  
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'destination_number', 'extension');
    }
}
