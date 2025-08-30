<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HotelRoom extends Model {

    use HasFactory, TraitUuid;

    protected $table = "hotel_rooms";

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid','extension_uuid','room_name',
        'occupancy_status','housekeeping_status'
    ];

    public function reservations(): HasMany 
    { 
        return $this->hasMany(HotelReservation::class); 
    }

    public function extension(): BelongsTo 
    { 
        return $this->belongsTo(Extensions::class,'extension_uuid'); 
    }
}
