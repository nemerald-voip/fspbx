<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flag extends Model
{
    use HasFactory, TraitUuid;

    public const TYPE_MANUAL = 'manual';
    public const TYPE_SCHEDULED = 'scheduled';

    protected $table = 'flags';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'name',
        'flag_number',
        'type',
        'timezone',
        'active',
        'pin_number',
        'blf_enabled',
        'enabled',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
        'blf_enabled' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FlagRule::class, 'flag_uuid', 'uuid');
    }

    public function allowedExtensions(): BelongsToMany
    {
        return $this->belongsToMany(Extensions::class, 'flag_extensions', 'flag_uuid', 'extension_uuid', 'uuid', 'extension_uuid');
    }

    public function isManual(): bool
    {
        return $this->type === self::TYPE_MANUAL;
    }

    /**
     * Ring groups and virtual receptionists that route on this flag.
     */
    public function usages(): array
    {
        $ringGroups = RingGroups::query()
            ->where('v_ring_groups.domain_uuid', $this->domain_uuid)
            ->join('flag_overrides', function ($join) {
                $join->on('flag_overrides.owner_uuid', '=', 'v_ring_groups.ring_group_uuid')
                    ->where('flag_overrides.owner_type', FlagOverride::OWNER_RING_GROUP)
                    ->where('flag_overrides.flag_uuid', $this->uuid);
            })
            ->orderBy('ring_group_extension')
            ->get(['ring_group_extension', 'ring_group_name'])
            ->toBase()
            ->map(fn ($row) => __('Ring Group') . ': ' . $row->ring_group_extension . ' - ' . $row->ring_group_name);

        $ivrs = IvrMenus::query()
            ->where('v_ivr_menus.domain_uuid', $this->domain_uuid)
            ->join('flag_overrides', function ($join) {
                $join->on('flag_overrides.owner_uuid', '=', 'v_ivr_menus.ivr_menu_uuid')
                    ->where('flag_overrides.owner_type', FlagOverride::OWNER_IVR_MENU)
                    ->where('flag_overrides.flag_uuid', $this->uuid);
            })
            ->orderBy('ivr_menu_extension')
            ->get(['ivr_menu_extension', 'ivr_menu_name'])
            ->toBase()
            ->map(fn ($row) => __('Virtual Receptionist') . ': ' . $row->ivr_menu_extension . ' - ' . $row->ivr_menu_name);

        return $ringGroups->merge($ivrs)->values()->all();
    }

    public function generateUniqueFlagNumber(): string
    {
        $used = static::query()
            ->where('domain_uuid', $this->domain_uuid ?? session('domain_uuid'))
            ->pluck('flag_number')
            ->flip();

        $number = 1;
        while (isset($used[(string) $number])) {
            $number++;
        }

        return (string) $number;
    }
}
