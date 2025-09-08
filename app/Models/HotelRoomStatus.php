<?php
// app/Models/HotelRoomStatus.php

namespace App\Models;

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
        'arrival_date'   => 'date',
        'departure_date' => 'date',
    ];

    /** Helpful virtuals */
    protected $appends = ['guest_full_name'];

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


}
