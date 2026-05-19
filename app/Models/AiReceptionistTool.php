<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiReceptionistTool extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'ai_receptionist_tools';
    protected $primaryKey = 'tool_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'headers' => 'array',
        'request_schema' => 'array',
        'enabled' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(AiReceptionist::class, 'ai_receptionist_uuid', 'ai_receptionist_uuid');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiReceptionistToolRun::class, 'tool_uuid', 'tool_uuid');
    }
}
