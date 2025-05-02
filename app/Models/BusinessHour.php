<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function exceptions(): HasMany
    {
        return $this->hasMany(BusinessHourException::class, 'business_hour_uuid', 'uuid');
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
