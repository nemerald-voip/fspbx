<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WakeupCall extends Model
{
    use HasFactory;

    protected $table = 'wakeup_calls';

    protected $primaryKey = 'uuid';

    public $incrementing = false; // UUID is the primary key

    protected $keyType = 'string'; // UUIDs are stored as strings

    protected $fillable = [
        'uuid',
        'domain_uuid',
        'extension_uuid',
        'wake_up_time',
        'next_attempt_at', // Next attempt, adjusted for snooze or failures
        'recurring', // Boolean flag for daily recurrence
        'status', // Call status: scheduled, in_progress, completed, failed
        'retry_count', // Number of retries before failure
    ];

    protected $casts = [
        'uuid' => 'string',
        'domain_uuid' => 'string',
        'extension_uuid' => 'string',
        'wake_up_time' => 'datetime',
        'next_attempt_at' => 'datetime',
        'recurring' => 'boolean',
    ];

    /**
     * Accessor: Get wake-up time formatted
     */
    public function getWakeUpTimeFormattedAttribute()
    {
        return $this->formatTime($this->wake_up_time);
    }

    /**
     * Accessor: Get next attempt formatted
     */
    public function getNextAttemptAtFormattedAttribute()
    {
        return $this->formatTime($this->next_attempt_at);
    }

    /**
     * Accessor: Get destroy route
     */
    public function destroyRoute(): Attribute
    {
        return Attribute::get(fn() => route('wakeup-calls.destroy', ['wakeup_call' => $this->uuid]));
    }

    /**
     * Helper function to format time with domain timezone
     */
    private function formatTime($timestamp)
    {
        if (!$timestamp || !$this->domain_uuid) {
            return null;
        }

        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::parse($timestamp)
            ->setTimezone($timeZone)
            ->format('g:i:s A M d, Y');
    }


    /**
     * Get extension model
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
    }

    /**
     * Get the domain to which this model belongs
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
