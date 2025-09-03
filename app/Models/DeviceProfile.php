<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceProfile extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_device_profiles";

    public $timestamps = false;

    protected $primaryKey = 'device_profile_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the Device Profile Key object associated with this profile.
     *  returns Eloquent Object
     */
    public function keys(): HasMany
    {
        return $this->hasMany(DeviceProfileKey::class, 'device_profile_uuid', 'device_profile_uuid');
    }


}
