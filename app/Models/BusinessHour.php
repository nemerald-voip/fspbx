<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use App\Models\BusinessHourPeriod;
use App\Models\BusinessHourHoliday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessHour extends Model
{
    use HasFactory, TraitUuid;

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
        // Business Hours will have extensions in the range between 9200 and 9249 by default
        $rangeStart = 9200;
        $rangeEnd = 9249;

        $domainUuid = session('domain_uuid');

        // Fetch all used extensions from Dialplans, Voicemails, and Extensions
        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )
            ->unique();

        // Find the first available extension
        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains($ext)) {
                // This is your unique extension
                $uniqueExtension = $ext;
                break;
            }
        }

        if (isset($uniqueExtension)) {
            return (string) $uniqueExtension;
        }

        // Return null if unable to generate a unique sequence number
        return null;
    }
}
