<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicStreams extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_streams";

    public $timestamps = false;

    protected $primaryKey = 'stream_uuid';

    protected $keyType = 'string';
}
