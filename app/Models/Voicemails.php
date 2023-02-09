<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
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

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * Get the extension voicemail belongs to.
     */
    public function extension()
    {
        return $this->hasOne(Extensions::class,'extension','voicemail_id')
            ->where('domain_uuid', $this->domain_uuid);
    }

    /**
     * Get the voicemail destinations belongs to.
     */
    public function greetings()
    {
        return $this->hasMany(VoicemailGreetings::class,'voicemail_id','voicemail_id')
            ->where('domain_uuid', $this->domain_uuid);
    }

    
    /**
     * Get all messages for this voicemail.
     */
    public function messages()
    {
        return $this->hasMany(VoicemailMessages::class,'voicemail_uuid','voicemail_uuid')
            ->where('domain_uuid', $this->domain_uuid);
    }

    /**
     * Get the voicemail destinations belongs to.
     */
    public function voicemail_destinations()
    {
        return $this->hasMany(VoicemailDestinations::class,'voicemail_uuid','voicemail_uuid');
    }


    /**
     * Get all forward destinations for this voicemail
     *      
    */
    public function forward_destinations()
    {

        $voicemail_destinations = VoicemailDestinations::where ('voicemail_uuid', $this->voicemail_uuid)
        ->get([
            'voicemail_uuid_copy',
        ]);

        $destinations = collect();
        foreach ($voicemail_destinations as $voicemail_destination) {
            $destinations->push($voicemail_destination->voicemail);
        }

        return $destinations;

    }

    /**
     * Get the domain to which this voicemail belongs 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class,'domain_uuid','domain_uuid');
    }

}
