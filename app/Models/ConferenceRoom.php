<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConferenceRoom extends Model
{
    use HasFactory;

    protected $table = "v_conference_rooms";

    public $timestamps = false;

    protected $primaryKey = 'conference_room_uuid';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'conference_room_uuid',
        'conference_center_uuid',
        'conference_room_name',
        'profile',
        'record',
        'moderator_pin',
        'participant_pin',
        'max_members',
        'start_datetime',
        'stop_datetime',
        'wait_mod',
        'moderator_endconf',
        'announce_name',
        'announce_recording',
        'announce_count',
        'sounds',
        'mute',
        'created',
        'created_by',
        'email_address',
        'account_code',
        'enabled',
        'description',
    ];

    public function conferenceCenter(): BelongsTo
    {
        return $this->belongsTo(ConferenceCenter::class, 'conference_center_uuid', 'conference_center_uuid');
    }

    public function roomUsers(): HasMany
    {
        return $this->hasMany(ConferenceRoomUser::class, 'conference_room_uuid', 'conference_room_uuid');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
