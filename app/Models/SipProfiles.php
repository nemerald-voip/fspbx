<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipProfiles extends Model
{
    use HasFactory;

    protected $table = "v_sip_profiles";

    public $timestamps = false;

    protected $primaryKey = 'sip_profile_uuid';

    protected $keyType = 'string';
}
