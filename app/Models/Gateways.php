<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gateways extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_gateways";

    protected $primaryKey = 'gateway_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gateway_uuid',
        'domain_uuid',
        'gateway',
        'username',
        'password',
        'distinct_to',
        'auth_username',
        'realm',
        'from_user',
        'from_domain',
        'proxy',
        'register_proxy',
        'outbound_proxy',
        'expire_seconds',
        'register',
        'register_transport',
        'extension',
        'ping',
        'ping_min',
        'ping_max',
        'caller_id_in_from',
        'supress_cng',
        'sip_cid_type',
        'codec_prefs',
        'channels',
        'extension_in_contact',
        'context',
        'profile',
        'hostname',
        'enabled',
        'description',
        'contact_in_ping',
        'contact_params',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            // unset($model->created_at_formatted);
            // unset($model->source_formatted);
            // unset($model->destination_formatted);
            // unset($model->send_notify_path);
        });

        static::retrieved(function ($model) {

            // $model->destroy_route = route('devices.destroy', $model);

            // return $model;
        });
    }


    /**
     * Get domain that this message settings belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

}
