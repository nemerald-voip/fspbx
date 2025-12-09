<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceUser extends Model
{
    use HasFactory;

    protected $table = "v_conference_users";

    public $timestamps = false;

    protected $primaryKey = 'conference_user_uuid';

    protected $keyType = 'string';
}
