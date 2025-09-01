<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelRoom extends Model {

    use HasFactory, TraitUuid;

    protected $table = "hotel_rooms";

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid','extension_uuid','room_name',
    ];

    public function extension(): BelongsTo 
    { 
        return $this->belongsTo(Extensions::class,'extension_uuid'); 
    }

    public function status(): HasOne 
    { 
        return $this->hasOne(HotelRoomStatus::class, 'hotel_room_uuid', 'uuid');
    }
}
