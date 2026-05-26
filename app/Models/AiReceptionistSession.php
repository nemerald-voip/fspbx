<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiReceptionistSession extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'ai_receptionist_sessions';
    protected $primaryKey = 'session_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(AiReceptionist::class, 'ai_receptionist_uuid', 'ai_receptionist_uuid');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(AiReceptionistSetting::class, 'setting_uuid', 'setting_uuid');
    }

    public function toolRuns(): HasMany
    {
        return $this->hasMany(AiReceptionistToolRun::class, 'session_uuid', 'session_uuid');
    }

    public function warmTransfers(): HasMany
    {
        return $this->hasMany(AiReceptionistWarmTransfer::class, 'session_uuid', 'session_uuid');
    }
}
