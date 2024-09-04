<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conferences extends Model
{
    use HasFactory;

    protected $table = "v_conferences";

    public $timestamps = false;

    protected $primaryKey = 'conference_uuid';

    protected $keyType = 'string';
}

