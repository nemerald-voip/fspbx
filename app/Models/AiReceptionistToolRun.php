<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReceptionistToolRun extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'ai_receptionist_tool_runs';
    protected $primaryKey = 'tool_run_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiReceptionistSession::class, 'session_uuid', 'session_uuid');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(AiReceptionistTool::class, 'tool_uuid', 'tool_uuid');
    }
}
