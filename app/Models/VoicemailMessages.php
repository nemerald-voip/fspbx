<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoicemailMessages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_messages";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * Get the voicemial this message belongs to.
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class,'voicemail_uuid','voicemail_uuid');
    }
}
