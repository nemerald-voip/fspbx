<?php

namespace App\Models;

use App\Models\Extensions;
use App\Models\VoicemailDestinations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voicemails extends Model
{
    use HasFactory, \App\Models\Traits\GeneratesUniqueExtensions, \App\Models\Traits\TraitUuid;

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
        $copyUuids = array_values(array_unique(array_filter($copyUuids)));
        $domainUuid = $this->domain_uuid ?: session('domain_uuid');

        $allowedCopyUuids = self::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('voicemail_uuid', $copyUuids)
            ->pluck('voicemail_uuid')
            ->all();

        $allowedLookup = array_flip($allowedCopyUuids);
        $copyUuids = array_values(array_filter(
            $copyUuids,
            fn ($copyUuid) => isset($allowedLookup[$copyUuid])
        ));

        // Remove all current destinations
        VoicemailDestinations::where('voicemail_uuid', $this->voicemail_uuid)->delete();

        // Add the new destinations
        foreach ($copyUuids as $copyUuid) {
            $destination = new VoicemailDestinations();
            $destination->domain_uuid = $domainUuid;
            $destination->voicemail_uuid = $this->voicemail_uuid;
            $destination->voicemail_uuid_copy = $copyUuid;
            $destination->save();
        }
    }

    public function vmNotifyProfile()
    {
        return $this->hasOne(VmNotifyProfile::class,
            'voicemail_uuid',
            'voicemail_uuid'
        );
    }


    /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {
        return $this->firstAvailableExtensionInRange(9100, 9149);
    }
}
