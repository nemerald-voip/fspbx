<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomerApiSettings extends Model
{
    public const CONNECTION_UUID = 'c0d4ab13-0a82-4f50-a5be-2f5d621d374e';

    protected $table = 'homer_api_settings';

    protected $primaryKey = 'homer_api_setting_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['api_token'];

    protected $casts = ['enabled' => 'boolean', 'api_token' => 'encrypted'];
}
