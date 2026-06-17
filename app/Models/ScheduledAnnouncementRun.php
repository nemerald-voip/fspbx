<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledAnnouncementRun extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'scheduled_announcement_runs';
    protected $primaryKey = 'scheduled_announcement_run_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'scheduled_announcement_run_uuid',
        'domain_uuid',
        'scheduled_announcement_schedule_uuid',
        'scheduled_announcement_event_uuid',
        'recording_filename',
        'occurrence_key',
        'scheduled_for',
        'claimed_at',
        'executed_at',
        'status',
        'claimed_by_hostname',
        'executed_by_hostname',
        'dns_answers',
        'esl_command',
        'esl_response',
        'error_text',
        'manual',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'claimed_at' => 'datetime',
        'executed_at' => 'datetime',
        'dns_answers' => 'array',
        'manual' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(ScheduledAnnouncementEvent::class, 'scheduled_announcement_event_uuid', 'scheduled_announcement_event_uuid');
    }
}
