<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceControlDetail extends Model
{
    use HasFactory;

    protected $table = 'v_conference_control_details';

    public $timestamps = false;

    protected $primaryKey = 'conference_control_detail_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conference_control_detail_uuid',
        'conference_control_uuid',
        'control_digits',
        'control_action',
        'control_data',
        'control_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];
}
