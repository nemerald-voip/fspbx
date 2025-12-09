<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceRoomUser extends Model
{
    use HasFactory;

    protected $table = "v_conference_room_users";

    public $timestamps = false;

    protected $primaryKey = 'conference_room_user_uuid';

    protected $keyType = 'string';
}
