<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceProfileKey extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_profile_keys";

    public $timestamps = false;

    protected $primaryKey = 'device_profile_key_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
