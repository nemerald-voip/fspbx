<?php

namespace App\Models;

use App\Models\Traits\GeneratesUniqueExtensions;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicRoute extends Model
{
    use GeneratesUniqueExtensions, TraitUuid;

    public const SOURCE_CALLER_DESTINATION = 'caller_destination';

    protected $table = 'dynamic_routes';
    protected $primaryKey = 'dynamic_route_uuid';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'name',
        'extension',
        'source',
        'context',
        'default_destination_type',
        'default_destination_value',
        'default_destination_label',
        'enabled',
        'description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(DynamicRouteRule::class, 'dynamic_route_uuid', 'dynamic_route_uuid')
            ->orderBy('rule_order');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        return $this->firstAvailableExtensionInRange(9500, 9549);
    }

    public function getDefaultDestinationTargetAttribute(): ?array
    {
        if (! filled($this->default_destination_value)) {
            return null;
        }

        return [
            'value' => $this->default_destination_value,
            'extension' => $this->default_destination_value,
            'name' => $this->default_destination_label ?: $this->default_destination_value,
        ];
    }
}
