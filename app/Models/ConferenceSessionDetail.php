<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceSessionDetail extends Model
{
    use HasFactory;

    protected $table = "v_conference_session_details";

    public $timestamps = false;

    protected $primaryKey = 'conference_session_detail_uuid';

    protected $keyType = 'string';
}
