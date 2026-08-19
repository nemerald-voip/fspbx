<?php

namespace App\Models;

use App\Models\Traits\GeneratesUniqueExtensions;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgent extends Model
{
    use GeneratesUniqueExtensions, TraitUuid;

    protected $table = 'ai_agents';
    protected $primaryKey = 'ai_agent_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'ai_agent_uuid',
        'domain_uuid',
        'dialplan_uuid',
        'name',
        'extension',
        'provider',
        'provider_phone_number',
        'inbound_agent_id',
        'inbound_agent_name',
        'outbound_agent_id',
        'outbound_agent_name',
        'recording_policy',
        'enabled',
        'provisioning_status',
        'provisioning_error',
        'last_synced_at',
        'description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function generateUniqueSequenceNumber(?string $domainUuid = null): ?string
    {
        return $this->firstAvailableExtensionInRange(9450, 9499, $domainUuid);
    }

    public function isRoutable(): bool
    {
        return $this->enabled && $this->provisioning_status === 'synced';
    }
}
