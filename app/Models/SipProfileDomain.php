<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipProfileDomain extends Model
{
    use HasFactory;

    protected $table = 'v_sip_profile_domains';

    public $timestamps = false;

    protected $primaryKey = 'sip_profile_domain_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function sipProfile()
    {
        return $this->belongsTo(SipProfiles::class, 'sip_profile_uuid', 'sip_profile_uuid');
    }
}
