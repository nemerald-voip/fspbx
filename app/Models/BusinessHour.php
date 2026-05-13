<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use App\Models\Traits\GeneratesUniqueExtensions;
use App\Models\BusinessHourPeriod;
use App\Models\BusinessHourHoliday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessHour extends Model
{
    use HasFactory, GeneratesUniqueExtensions, TraitUuid;

    protected $table = 'business_hours';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'name',
        'extension',
        'timezone',
        'after_hours_action',
        'after_hours_target_type',
        'after_hours_target_id',
        'context',
        'description',
        'enabled',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * The periods (regular open intervals) for this business hour set.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(BusinessHourPeriod::class, 'business_hour_uuid', 'uuid');
    }

    /**
     * The one-off exceptions (holidays or overrides).
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(BusinessHourHoliday::class, 'business_hour_uuid', 'uuid');
    }


    public function after_hours_target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {
        return $this->firstAvailableExtensionInRange(9200, 9249);
    }
}
