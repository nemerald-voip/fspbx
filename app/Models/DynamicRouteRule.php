<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicRouteRule extends Model
{
    use TraitUuid;

    protected $table = 'dynamic_route_rules';
    protected $primaryKey = 'dynamic_route_rule_uuid';

    protected $fillable = [
        'dynamic_route_uuid',
        'match_value',
        'destination_type',
        'destination_value',
        'destination_label',
        'rule_order',
    ];

    protected $appends = ['destination_target'];

    public function dynamicRoute(): BelongsTo
    {
        return $this->belongsTo(DynamicRoute::class, 'dynamic_route_uuid', 'dynamic_route_uuid');
    }

    public function getDestinationTargetAttribute(): ?array
    {
        if (! filled($this->destination_value)) {
            return null;
        }

        return [
            'value' => $this->destination_value,
            'extension' => $this->destination_value,
            'name' => $this->destination_label ?: $this->destination_value,
        ];
    }
}
