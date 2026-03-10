<?php

namespace App\Models;

use App\Models\Devices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceLines extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_lines";

    public $timestamps = false;

    protected $primaryKey = 'device_line_uuid';
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
        'line_number',
        'server_address',
        'outbound_proxy_primary',
        'outbound_proxy_secondary',
        'server_address_primary',
        'server_address_secondary',
        'display_name',
        'user_id',
        'auth_id',
        'password',
        'sip_port',
        'sip_transport',
        'register_expires',
        'shared_line',
        'external_line',
        'enabled',
        'label',
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


    /**
     * Get the Device object associated with this device line.
     *  returns Eloquent Object
     */
    public function device()
    {
        return $this->belongsTo(Devices::class, 'device_uuid', 'device_uuid');
    }


    // Extension relationship is NOT a simple foreign key, so use custom logic
    public function extension()
    {
        return $this->hasOne(
            Extensions::class,
            // local key, foreign key
            'extension','auth_id'
        );
    }
}
