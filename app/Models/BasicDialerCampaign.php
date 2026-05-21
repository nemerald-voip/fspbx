<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasicDialerCampaign extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'basic_dialer_campaigns';

    protected $primaryKey = 'basic_dialer_campaign_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'max_concurrent_calls' => 'integer',
        'seconds_between_calls' => 'integer',
        'retry_limit' => 'integer',
        'retry_delay_minutes' => 'integer',
        'originate_timeout' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'stopped_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(BasicDialerContactList::class, 'basic_dialer_contact_list_uuid', 'basic_dialer_contact_list_uuid');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BasicDialerCampaignRecipient::class, 'basic_dialer_campaign_uuid', 'basic_dialer_campaign_uuid');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(BasicDialerCampaignAttempt::class, 'basic_dialer_campaign_uuid', 'basic_dialer_campaign_uuid');
    }
}
