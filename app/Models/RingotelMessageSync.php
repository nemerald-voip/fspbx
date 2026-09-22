<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RingotelMessageSync extends Model
{
    protected $primaryKey = 'message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['parts' => 'array'];
}
