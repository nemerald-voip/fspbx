<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasicDialerContactList extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'basic_dialer_contact_lists';

    protected $primaryKey = 'basic_dialer_contact_list_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BasicDialerContact::class, 'basic_dialer_contact_list_uuid', 'basic_dialer_contact_list_uuid');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(BasicDialerCampaign::class, 'basic_dialer_contact_list_uuid', 'basic_dialer_contact_list_uuid');
    }
}
