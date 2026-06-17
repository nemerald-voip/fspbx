<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class BillingPrice extends Model
{
    use TraitUuid;

    protected $table = 'billing_prices';
    protected $primaryKey = 'price_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'price_uuid',
        'product_uuid',
        'provider',
        'provider_price_id',
        'livemode',
        'active',
        'currency',
        'unit_amount_cents',
        'billing_scheme',
        'tiers_mode',
        'tiers',
        'line_type',
        'interval',
        'interval_count',
        'nickname',
        'lookup_key',
        'tax_behavior',
        'recurring_usage_type',
        'transform_quantity',
        'metadata',
        'synced_at',
        'last_sync_error',
    ];

    protected $casts = [
        'livemode' => 'boolean',
        'active' => 'boolean',
        'tiers' => 'array',
        'transform_quantity' => 'array',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(BillingProduct::class, 'product_uuid', 'uuid');
    }

    public function snapshot(): array
    {
        return [
            'price_uuid' => $this->price_uuid,
            'provider_price_id' => $this->provider_price_id,
            'currency' => $this->currency,
            'unit_amount_cents' => $this->unit_amount_cents,
            'billing_scheme' => $this->billing_scheme,
            'tiers_mode' => $this->tiers_mode,
            'tiers' => $this->tiers,
            'line_type' => $this->line_type,
            'interval' => $this->interval,
            'interval_count' => $this->interval_count,
            'nickname' => $this->nickname,
            'lookup_key' => $this->lookup_key,
            'tax_behavior' => $this->tax_behavior,
            'recurring_usage_type' => $this->recurring_usage_type,
            'product' => $this->product ? [
                'product_uuid' => $this->product->uuid,
                'provider_product_id' => $this->product->provider_product_id,
                'name' => $this->product->name,
                'description' => $this->product->description,
                'unit_label' => $this->product->unit_label,
                'metadata' => $this->product->metadata ?: [],
            ] : null,
        ];
    }
}
