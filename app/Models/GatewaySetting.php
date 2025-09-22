<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewaySetting extends Model
{
    protected $table = "gateway_settings";
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';
    protected $fillable = ['uuid','gateway_uuid','setting_key','setting_value'];
}
