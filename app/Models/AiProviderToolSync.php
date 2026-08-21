<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class AiProviderToolSync extends Model
{
    use TraitUuid;

    protected $table = 'ai_provider_tool_syncs';
    protected $primaryKey = 'ai_provider_tool_sync_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'response_engine_version' => 'integer',
        'draft_agent_version' => 'integer',
        'published_agent_version' => 'integer',
        'last_attempt_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}
