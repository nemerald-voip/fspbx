<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceKey extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'device_keys';

    protected $primaryKey = 'device_key_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_uuid',
        'key_index',
        'key_type',
        'key_value',
        'key_label',
    ];
}
