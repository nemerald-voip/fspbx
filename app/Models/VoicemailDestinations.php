<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoicemailDestinations extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_destinations";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * Get the voicmeial this destination belongs to.
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class,'voicemail_uuid','voicemail_uuid_copy');
    }

}
