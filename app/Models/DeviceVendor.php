<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceVendor extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_vendors";

    public $timestamps = false;

    protected $primaryKey = 'device_vendor_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
