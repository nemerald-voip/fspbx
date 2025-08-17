<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';
    protected $fillable = ['uuid', 'slug', 'name', 'is_enabled', 'sort_order'];



    public function settings()
    {
        return $this->hasMany(\App\Models\GatewaySetting::class, 'gateway_uuid', 'uuid');
    }
}
