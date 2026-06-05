<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class BillingProduct extends Model
{
    use TraitUuid;

    protected $table = 'billing_products';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'id',
        'provider',
        'provider_product_id',
        'default_price_ref',
        'livemode',
        'active',
        'name',
        'description',
        'type',
        'statement_descriptor',
        'tax_code',
        'unit_label',
        'url',
        'metadata',
        'images',
        'marketing_features',
        'package_dimensions',
        'shippable',
        'external_created_at',
        'external_updated_at',
        'synced_at',
        'last_sync_error',
    ];

    protected $casts = [
        'livemode'           => 'boolean',
        'active'             => 'boolean',
        'metadata'           => 'array',
        'images'             => 'array',
        'marketing_features' => 'array',
        'package_dimensions' => 'array',
        'shippable'          => 'boolean',
        'external_created_at'=> 'datetime',
        'external_updated_at'=> 'datetime',
        'synced_at'          => 'datetime',
    ];

    public function prices()
    {
        return $this->hasMany(BillingPrice::class, 'product_uuid', 'uuid');
    }
}
