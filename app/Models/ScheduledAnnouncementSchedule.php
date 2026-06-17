<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledAnnouncementSchedule extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'scheduled_announcement_schedules';
    protected $primaryKey = 'scheduled_announcement_schedule_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'scheduled_announcement_schedule_uuid',
        'domain_uuid',
        'name',
        'description',
        'timezone',
        'recording_filename',
        'extension_uuids',
        'busy_extension_behavior',
        'enabled',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'extension_uuids' => 'array',
        'enabled' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function events()
    {
        return $this->hasMany(ScheduledAnnouncementEvent::class, 'scheduled_announcement_schedule_uuid', 'scheduled_announcement_schedule_uuid')
            ->orderBy('sort_order')
            ->orderBy('time_of_day');
    }

    public function exceptions()
    {
        return $this->hasMany(ScheduledAnnouncementException::class, 'scheduled_announcement_schedule_uuid', 'scheduled_announcement_schedule_uuid');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
