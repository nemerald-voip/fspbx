<?php

namespace App\Models;

use App\Models\Extensions;
use App\Models\VoicemailDestinations;
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
        'voicemail_tutorial',
        'voicemail_recording_instructions',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];


    public function getMessagesRouteAttribute()
    {
        return route('voicemails.messages.index', $this);
    }

    public function setDomainUuidAttribute($value)
    {
        $this->attributes['domain_uuid'] = $value ?: session('domain_uuid');
    }

    // Accessor for greeting_id
    public function getGreetingIdAttribute($value)
    {
        // Return -1 if greeting_id is null and has been requested
        return $value === null ? '-1' : (string) $value;
    }

    // Mutator for greeting_id
    public function setGreetingIdAttribute($value)
    {
        // Convert the value to null if it is '-1', otherwise convert it to an integer
        $this->attributes['greeting_id'] = $value === '-1' ? null : (int) $value;
    }

    /**
     * Get the extension voicemail belongs to.
     * This gets you “all possible” extensions where ID matches voicemail id, regardless of domain.
     * Further filtering by domain is REQUIRED to avoid false positives and PERFORMANCE ISSUES  
     */
    public function extension()
    {
        return $this->hasOne(Extensions::class, 'extension', 'voicemail_id');

    }

    /**
     * Get the voicemail greetings.
     */
    public function greetings($domain_uuid = null)
    {
        $domain_uuid = $domain_uuid ?: session('domain_uuid');
        return $this->hasMany(VoicemailGreetings::class, 'voicemail_id', 'voicemail_id')
            ->where('domain_uuid', $domain_uuid);
    }


    /**
     * Get all messages for this voicemail.
     */
    public function messages()
    {
        return $this->hasMany(VoicemailMessages::class, 'voicemail_uuid', 'voicemail_uuid');
    }

    /**
     * Get the voicemail destinations belongs to.
     */
    public function voicemail_destinations()
    {
        return $this->hasMany(VoicemailDestinations::class, 'voicemail_uuid', 'voicemail_uuid');
    }


    /**
     * Get all forward destinations for this voicemail
     *
     */
    public function forward_destinations()
    {

        $voicemail_destinations = VoicemailDestinations::where('voicemail_uuid', $this->voicemail_uuid)
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
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function getId()
    {
        return $this->voicemail_id;
    }

    public function getName()
    {
        return $this->voicemail_id . ' - ' . $this->voicemail_mail_to;
    }


    public function syncCopies(array $copyUuids)
    {
        // Remove all current destinations
        VoicemailDestinations::where('voicemail_uuid', $this->voicemail_uuid)->delete();

        // Add the new destinations
        foreach ($copyUuids as $copyUuid) {
            $destination = new VoicemailDestinations();
            $destination->voicemail_uuid = $this->voicemail_uuid;
            $destination->voicemail_uuid_copy = $copyUuid;
            $destination->save();
        }
    }



    /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {

        // Voicemails will have extensions in the range between 9100 and 9150 by default
        $rangeStart = 9100;
        $rangeEnd = 9149;

        $domainUuid = session('domain_uuid');

        // Fetch all used extensions from Dialplans, Voicemails, and Extensions
        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )
            ->unique();

        // Find the first available extension
        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains($ext)) {
                // This is your unique extension
                $uniqueExtension = $ext;
                break;
            }
        }

        if (isset($uniqueExtension)) {
            return (string) $uniqueExtension;
        }

        // Return null if unable to generate a unique sequence number
        return null;
    }
}
