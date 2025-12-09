<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceSession extends Model
{
    use HasFactory;

    protected $table = "v_conference_sessions";

    public $timestamps = false;

    protected $primaryKey = 'conference_session_uuid';

    protected $keyType = 'string';
}
