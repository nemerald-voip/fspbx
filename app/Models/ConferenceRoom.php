<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceRoom extends Model
{
    use HasFactory;

    protected $table = "v_conference_rooms";

    public $timestamps = false;

    protected $primaryKey = 'conference_room_uuid';

    protected $keyType = 'string';
}
