<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageApiRequest extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'message_api_request_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['response' => 'array', 'expires_at' => 'datetime'];
}
