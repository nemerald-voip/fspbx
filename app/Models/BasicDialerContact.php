<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasicDialerContact extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'basic_dialer_contacts';

    protected $primaryKey = 'basic_dialer_contact_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
        'enabled' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(BasicDialerContactList::class, 'basic_dialer_contact_list_uuid', 'basic_dialer_contact_list_uuid');
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(BasicDialerCampaignRecipient::class, 'basic_dialer_contact_uuid', 'basic_dialer_contact_uuid');
    }
}
