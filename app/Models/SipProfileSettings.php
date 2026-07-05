<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipProfileSettings extends Model
{
    use HasFactory;

    protected $table = "v_sip_profile_settings";

    public $timestamps = false;

    protected $primaryKey = 'sip_profile_setting_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    // Define the inverse relationship with v_sip_profiles
    public function sipProfile()
    {
        return $this->belongsTo(SipProfiles::class, 'sip_profile_uuid', 'sip_profile_uuid');
    }
}
