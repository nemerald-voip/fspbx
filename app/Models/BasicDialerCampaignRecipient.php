<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasicDialerCampaignRecipient extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'basic_dialer_campaign_recipients';

    protected $primaryKey = 'basic_dialer_campaign_recipient_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BasicDialerCampaign::class, 'basic_dialer_campaign_uuid', 'basic_dialer_campaign_uuid');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(BasicDialerContact::class, 'basic_dialer_contact_uuid', 'basic_dialer_contact_uuid');
    }

    public function attemptRecords(): HasMany
    {
        return $this->hasMany(BasicDialerCampaignAttempt::class, 'basic_dialer_campaign_recipient_uuid', 'basic_dialer_campaign_recipient_uuid');
    }
}
