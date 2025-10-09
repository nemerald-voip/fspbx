<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPendingAction extends Model
{
    use HasUuids;

    protected $table = 'hotel_pending_actions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'hotel_room_uuid',
        'smdr_type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function room(): BelongsTo 
    { 
        return $this->belongsTo(HotelRoom::class,'hotel_room_uuid'); 
    }
}
