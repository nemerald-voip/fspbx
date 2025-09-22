<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingProduct extends Model
{

    protected $table = 'billing_products';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
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
    ];
}
