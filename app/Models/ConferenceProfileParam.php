<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceProfileParam extends Model
{
    use HasFactory;

    protected $table = 'v_conference_profile_params';

    public $timestamps = false;

    protected $primaryKey = 'conference_profile_param_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conference_profile_param_uuid',
        'conference_profile_uuid',
        'profile_param_name',
        'profile_param_value',
        'profile_param_enabled',
        'profile_param_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];
}
