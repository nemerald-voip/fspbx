<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoicemailDestinations extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_destinations";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->insert_date = date('Y-m-d H:i:s');
            $model->insert_user = session('user_uuid');
        });

        static::saving(function ($model) {
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }
            if (!$model->voicemail_destination_uuid) {
                $model->voicemail_destination_uuid = Str::uuid();
            }

        });
    }

    /**
     * Get the voicmeial this destination belongs to.
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class,'voicemail_uuid','voicemail_uuid_copy');
    }

}
