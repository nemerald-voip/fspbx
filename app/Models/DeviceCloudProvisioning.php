<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceCloudProvisioning extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "device_cloud_provisioning";

    public $timestamps = false;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_uuid',
        'provider',
        'status',
        'error'
    ];

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Devices::class, 'device_uuid', 'device_uuid');
    }
}
