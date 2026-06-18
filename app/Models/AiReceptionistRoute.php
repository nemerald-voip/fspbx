<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReceptionistRoute extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'ai_receptionist_routes';
    protected $primaryKey = 'route_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'match_phrases' => 'array',
        'collected_fields' => 'array',
        'notify_on_failed_warm_transfer' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(AiReceptionist::class, 'ai_receptionist_uuid', 'ai_receptionist_uuid');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
