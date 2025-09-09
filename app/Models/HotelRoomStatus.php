<?php
// app/Models/HotelRoomStatus.php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRoomStatus extends Model
{
    /** Table & key */
    protected $table = 'hotel_room_status';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /** Mass assignment */
    protected $fillable = [
        'uuid',
        'domain_uuid',
        'hotel_room_uuid',
        'occupancy_status',
        'housekeeping_status',
        'guest_first_name',
        'guest_last_name',
        'arrival_date',
        'departure_date',
    ];

    /** Casting */
    protected $casts = [
        'arrival_date'   => 'datetime',
        'departure_date' => 'datetime',
    ];

    /** Helpful virtuals */
    protected $appends = [
        'guest_full_name',
        'arrival_date_formatted',
        'departure_date_formatted',
    ];

    /** Relationships */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_uuid', 'uuid');
    }

    public function housekeepingDefinition(): BelongsTo
    {
        return $this->belongsTo(HotelHousekeepingDefinition::class, 'housekeeping_status', 'uuid');
    }

    /** Accessors */
    public function getGuestFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->guest_last_name, $this->guest_first_name]);
        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Localized, formatted arrival/departure (convert from stored UTC to domain tz).
     * Default format: Y-m-d H:i:s (change as you like)
     */
    public function getArrivalDateFormattedAttribute(): ?string
    {
        return $this->formatLocal($this->arrival_date);
    }

    public function getDepartureDateFormattedAttribute(): ?string
    {
        return $this->formatLocal($this->departure_date);
    }

    private function formatLocal(?Carbon $dt, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!$dt) return null;
        $tz = get_local_time_zone($this->domain_uuid) ?: 'UTC';
        // $dt is a Carbon (cast from DB). Assume stored as UTC; convert to local tz for display.
        return $dt->copy()->setTimezone($tz)->format($format);
    }
}
