<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recordings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_recordings";

    public $timestamps = false;

    protected $primaryKey = 'recording_uuid';

    protected $keyType = 'string';
}
