<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HotelReservation extends Model {
    
    use HasFactory, TraitUuid;

    protected $table = "hotel_reservations";

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid','hotel_room_uuid','guest_first_name','guest_last_name',
        'arrival_date','departure_date',
    ];
    public function room(): BelongsTo 
    { 
        return $this->belongsTo(HotelRoom::class,'hotel_room_uuid'); 
    }
}

