<?php

namespace App\Models;

use App\Models\Devices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_settings";

    public $timestamps = false;

    protected $primaryKey = 'device_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'device_uuid',
        'device_setting_category',
        'device_setting_subcategory',
        'device_setting_name',
        'device_setting_value',
        'device_setting_enabled',
        'device_setting_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->domain_uuid  = $model->domain_uuid ?? session('domain_uuid');
            $model->insert_date  = date('Y-m-d H:i:s');
            $model->insert_user  = session('user_uuid');
        });

        static::updating(function ($model) {
            $model->update_date  = date('Y-m-d H:i:s');
            $model->update_user  = session('user_uuid');
        });
    }


    /**
     * Get the Device object associated with this device line.
     *  returns Eloquent Object
     */
    public function device()
    {
        return $this->belongsTo(Devices::class, 'device_uuid', 'device_uuid');
    }
}
