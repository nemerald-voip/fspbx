<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceControl extends Model
{
    use HasFactory;

    protected $table = 'v_conference_controls';

    public $timestamps = false;

    protected $primaryKey = 'conference_control_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conference_control_uuid',
        'control_name',
        'control_enabled',
        'control_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];
}
