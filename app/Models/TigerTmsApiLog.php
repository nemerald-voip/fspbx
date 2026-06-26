<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TigerTmsApiLog extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'tigertms_api_logs';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'request_context' => 'array',
        'request_payload' => 'array',
        'response_body' => 'array',
    ];

    protected $appends = [
        'created_at_formatted',
    ];

    public function getCreatedAtFormattedAttribute(): ?string
    {
        if (! $this->created_at) {
            return null;
        }

        $timeZone = auth()->user()->time_zone;

        return Carbon::parse($this->created_at)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
