<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicOnHold extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_music_on_hold";

    public $timestamps = false;

    protected $primaryKey = 'music_on_hold_uuid';

    protected $keyType = 'string';
}
