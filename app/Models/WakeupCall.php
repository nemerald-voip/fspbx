<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->fax_date_formatted);
        });

        static::retrieved(function ($model) {
            $time_zone = get_local_time_zone($model->domain_uuid);
            if ($model->fax_date && $model->domain_uuid) {
                $model->fax_date_formatted = Carbon::parse($model->fax_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

            if ($model->fax_retry_date && $model->domain_uuid) {
                $model->fax_retry_date_formatted = Carbon::parse($model->fax_retry_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

            if ($model->fax_notify_date && $model->domain_uuid) {
                $model->fax_notify_date_formatted = Carbon::parse($model->fax_notify_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

            return $model;
        });
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
