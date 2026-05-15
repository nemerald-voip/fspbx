<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceKeyTemplate extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'device_key_templates';

    protected $primaryKey = 'device_key_template_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'name',
        'description',
        'enabled',
    ];

    public function keys(): HasMany
    {
        return $this->hasMany(DeviceKeyTemplateKey::class, 'device_key_template_uuid', 'device_key_template_uuid');
    }
}
