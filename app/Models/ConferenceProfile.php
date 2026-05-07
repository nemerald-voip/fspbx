<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceProfile extends Model
{
    use HasFactory;

    protected $table = 'v_conference_profiles';

    public $timestamps = false;

    protected $primaryKey = 'conference_profile_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conference_profile_uuid',
        'profile_name',
        'profile_enabled',
        'profile_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];
}
