<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMessageWebhookEvent extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'outbound_message_webhook_event_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'media_items' => 'array', 'prepared' => 'boolean', 'attempted_at' => 'datetime'];
}
