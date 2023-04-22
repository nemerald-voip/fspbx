<?php

namespace App\Models;

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

    public function extension()
    {
        return Extensions::where('extension', $this->attributes['label'])->first();
    }
}
