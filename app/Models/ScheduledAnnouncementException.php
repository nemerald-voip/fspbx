<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledAnnouncementException extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'scheduled_announcement_exceptions';
    protected $primaryKey = 'scheduled_announcement_exception_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'scheduled_announcement_exception_uuid',
        'domain_uuid',
        'scheduled_announcement_schedule_uuid',
        'exception_date',
        'comment',
    ];

    protected $casts = [
        'exception_date' => 'date',
    ];
}
