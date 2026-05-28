<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReceptionistWarmTransfer extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'ai_receptionist_warm_transfers';
    protected $primaryKey = 'warm_transfer_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiReceptionistSession::class, 'session_uuid', 'session_uuid');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(AiReceptionistRoute::class, 'route_uuid', 'route_uuid');
    }
}
