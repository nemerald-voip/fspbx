<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoicemailGreetings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_greetings";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_greeting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
