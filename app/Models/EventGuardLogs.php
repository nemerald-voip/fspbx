<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventGuardLogs extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_event_guard_logs";

    protected $primaryKey = 'event_guard_log_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
            unset($model->log_date_formatted);
        });

        static::retrieved(function ($model) {
            if ($model->log_date) {
                $time_zone = get_local_time_zone();

                $model->log_date_formatted = Carbon::parse($model->log_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');

            }
            // $model->destroy_route = route('devices.destroy', $model);

            return $model;
        });
    }



}
