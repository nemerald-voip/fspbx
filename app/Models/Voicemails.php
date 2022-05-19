<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voicemails extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemails";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'voicemail_uuid',
        'voicemail_id',
        'voicemail_password',
        'greeting_id',
        'voicemail_alternate_greet_id',
        'voicemail_mail_to',
        'voicemail_sms_to',
        'voicemail_transcription_enabled',
        'voicemail_attach_file',
        'voicemail_file',
        'voicemail_local_after_email',
        'voicemail_enabled',
        'voicemail_description',
        'voicemail_name_base64',
        'voicemail_tutorial'
    ];

    /**
     * Get the extension voicemail belongs to.
     */
    public function extension()
    {
        return $this->hasOne(Extensions::class,'extension','voicemail_id');
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];
}
