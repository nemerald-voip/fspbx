<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RingotelConversation extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $primaryKey = 'ringotel_conversation_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['observed_at' => 'datetime'];
}
