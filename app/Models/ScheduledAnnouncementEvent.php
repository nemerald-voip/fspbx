<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledAnnouncementEvent extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'scheduled_announcement_events';
    protected $primaryKey = 'scheduled_announcement_event_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'scheduled_announcement_event_uuid',
        'domain_uuid',
        'scheduled_announcement_schedule_uuid',
        'time_of_day',
        'weekdays',
        'sort_order',
    ];

    protected $casts = [
        'weekdays' => 'array',
    ];

    public function schedule()
    {
        return $this->belongsTo(ScheduledAnnouncementSchedule::class, 'scheduled_announcement_schedule_uuid', 'scheduled_announcement_schedule_uuid');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
