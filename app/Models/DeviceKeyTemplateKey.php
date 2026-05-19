<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceKeyTemplateKey extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'device_key_template_keys';

    protected $primaryKey = 'device_key_template_key_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_key_template_uuid',
        'key_area',
        'key_index',
        'key_type',
        'key_value',
        'key_label',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeviceKeyTemplate::class, 'device_key_template_uuid', 'device_key_template_uuid');
    }
}
