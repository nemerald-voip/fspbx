<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlagOverride extends Model
{
    use HasFactory, TraitUuid;

    public const OWNER_RING_GROUP = 'ring_group';
    public const OWNER_IVR_MENU = 'ivr_menu';

    protected $table = 'flag_overrides';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'owner_type',
        'owner_uuid',
        'flag_uuid',
        'override_app',
        'override_data',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class, 'flag_uuid', 'uuid');
    }
}
