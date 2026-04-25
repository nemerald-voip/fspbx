<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_ai_agents';

    public $timestamps = false;

    protected $primaryKey = 'ai_agent_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'agent_name',
        'agent_extension',
        'elevenlabs_agent_id',
        'elevenlabs_phone_number_id',
        'system_prompt',
        'first_message',
        'voice_id',
        'language',
        'agent_enabled',
        'description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function kbDocuments()
    {
        return $this->hasMany(AiAgentKbDocument::class, 'ai_agent_uuid', 'ai_agent_uuid');
    }

    public function getId()
    {
        return $this->agent_extension;
    }

    public function getName()
    {
        return $this->agent_extension . ' - ' . $this->agent_name;
    }

    public function getNameFormattedAttribute()
    {
        return $this->agent_extension . ' - ' . $this->agent_name;
    }

    /**
     * Generates a unique sequence number in the 9250-9299 range.
     */
    public function generateUniqueSequenceNumber(): ?string
    {
        $rangeStart = 9250;
        $rangeEnd = 9299;
        $domainUuid = session('domain_uuid');

        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)->pluck('extension')
            )
            ->map(fn($value) => (string) $value)
            ->unique()
            ->values();

        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains((string) $ext)) {
                return (string) $ext;
            }
        }

        return null;
    }
}
