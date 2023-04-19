<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class FollowMe extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_follow_me";

    public $timestamps = false;

    protected $primaryKey = 'follow_me_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'follow_me_uuid',
        'domain_uuid',
        'cid_name_prefix',
        'cid_number_prefix',
        'dial_string',
        'follow_me_enabled',
        'follow_me_ignore_busy',
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
        $this->fill($attributes);
    }

    public function followMeDestinations()
    {
        return $this->hasMany(FollowMeDestinations::class,'follow_me_uuid','follow_me_uuid');
    }
}
