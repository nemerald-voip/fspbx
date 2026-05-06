<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceRoomUser extends Model
{
    use HasFactory;

    protected $table = "v_conference_room_users";

    public $timestamps = false;

    protected $primaryKey = 'conference_room_user_uuid';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'conference_room_user_uuid',
        'domain_uuid',
        'conference_room_uuid',
        'user_uuid',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'user_uuid');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ConferenceRoom::class, 'conference_room_uuid', 'conference_room_uuid');
    }
}
