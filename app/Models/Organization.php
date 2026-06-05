<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use \App\Traits\HasContactInfo, TraitUuid; 

    protected $primaryKey = 'organization_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'organization_uuid',
        'domain_uuid',
        'name',
        'website',
        'notes',
        'billing_provider',
        'billing_provider_customer_id',
        'billing_livemode',
        'billing_enabled',
        'billing_synced_at',
        'billing_last_sync_error',
        'billing_metadata',
        'billing_invoice_prefix',
        'billing_next_invoice_sequence',
    ];

    protected $casts = [
        'billing_livemode' => 'boolean',
        'billing_enabled' => 'boolean',
        'billing_synced_at' => 'datetime',
        'billing_metadata' => 'array',
        'billing_next_invoice_sequence' => 'integer',
    ];

    // Relationships
    public function contacts() {
        return $this->hasMany(Contact::class, 'organization_uuid');
    }
    
    public function addresses() {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    public function emails() {
        return $this->morphMany(ContactEmail::class, 'emailable');
    }

    public function billingSnapshot(): array
    {
        $email = $this->emails()->where('label', 'billing')->value('email_address')
            ?: $this->emails()->where('label', 'work')->value('email_address');
        $address = $this->addresses()->where('label', 'billing')->first()
            ?: $this->addresses()->where('label', 'main')->first();

        return [
            'organization_uuid' => $this->organization_uuid,
            'provider_customer_id' => $this->billing_provider_customer_id,
            'name' => $this->name,
            'email' => $email,
            'address' => $address ? [
                'line1' => $address->street,
                'line2' => $address->extended,
                'city' => $address->city,
                'state' => $address->region,
                'postal_code' => $address->postal_code,
                'country' => $address->country_code,
            ] : null,
        ];
    }

}
