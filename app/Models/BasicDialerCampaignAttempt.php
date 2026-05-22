<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasicDialerCampaignAttempt extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'basic_dialer_campaign_attempts';

    protected $primaryKey = 'basic_dialer_campaign_attempt_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'attempt_number' => 'integer',
        'duration' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BasicDialerCampaign::class, 'basic_dialer_campaign_uuid', 'basic_dialer_campaign_uuid');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(BasicDialerCampaignRecipient::class, 'basic_dialer_campaign_recipient_uuid', 'basic_dialer_campaign_recipient_uuid');
    }
}
