<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundWebhookLog extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'outbound_webhook_log_uuid';
    protected $guarded = [];
    protected $hidden = ['delivery_key'];
    protected $casts = ['attempt_history' => 'array', 'attempted_at' => 'datetime'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
