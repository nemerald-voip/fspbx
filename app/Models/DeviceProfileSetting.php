<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceProfileSetting extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_profile_settings";

    public $timestamps = false;

    protected $primaryKey = 'device_profile_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
