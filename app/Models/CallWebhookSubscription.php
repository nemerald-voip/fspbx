<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class CallWebhookSubscription extends Model
{
    use TraitUuid;

    public const EVENT_RINGING = 'call.ringing';
    public const EVENT_ANSWERED = 'call.answered';
    public const EVENT_ENDED = 'call.ended';

    public const EVENTS = [
        self::EVENT_RINGING,
        self::EVENT_ANSWERED,
        self::EVENT_ENDED,
    ];

    protected $table = 'call_webhook_subscriptions';
    protected $primaryKey = 'call_webhook_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'call_webhook_uuid',
        'domain_uuid',
        'endpoint_url',
        'signing_secret',
        'enabled',
        'events',
    ];

    protected $casts = [
        'signing_secret' => 'encrypted',
        'enabled' => 'boolean',
        'events' => 'array',
    ];

    protected $hidden = [
        'signing_secret',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function accepts(string $eventType): bool
    {
        return $this->enabled && in_array($eventType, $this->events ?? [], true);
    }

    public function maskedSecret(): string
    {
        $secret = (string) $this->signing_secret;

        return $secret === '' ? '' : str_repeat('•', 24) . substr($secret, -4);
    }
}
