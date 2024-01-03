<?php

namespace App\Models;

use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Notifications\Notifiable;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CDR extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_xml_cdr";

    public $timestamps = false;

    protected $primaryKey = 'xml_cdr_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_name',
    ];

    public function getCallerIdNumberAttribute($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } else {
                return $value;
            }
        } catch (NumberParseException $e) {
            // Do nothing and leave the numbner as is
            return $value;
        }
    }

    public function getCallerDestinationAttribute($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } else {
                return $value;
            }
        } catch (NumberParseException $e) {
            // Do nothing and leave the numbner as is
            return $value;
        }
    }

    public function getDestinationNumberAttribute($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } else {
                return $value;
            }
        } catch (NumberParseException $e) {

            // Do nothing and leave the numbner as is
            return $value;
        }
    }

    public function getStartDateAttribute()
    {
        $utcDateTime = Carbon::createFromTimestamp($this->start_epoch, 'UTC');
        $localDateTime = $utcDateTime->setTimezone(get_local_time_zone(Session::get('domain_uuid')));

        return $localDateTime->format('M d, Y');
    }

    public function getStartTimeAttribute()
    {
        $utcDateTime = Carbon::createFromTimestamp($this->start_epoch, 'UTC');
        $localDateTime = $utcDateTime->setTimezone(get_local_time_zone(Session::get('domain_uuid')));

        return $localDateTime->format('g:i:s A');
    }

    public function getDurationAttribute($value)
    {
        // Calculate hours, minutes, and seconds
        $hours = floor($value / 3600);
        $minutes = floor(($value % 3600) / 60);
        $seconds = $value % 60;

        // Format each component to be two digits with leading zeros if necessary
        $formattedHours = str_pad($hours, 2, "0", STR_PAD_LEFT);
        $formattedMinutes = str_pad($minutes, 2, "0", STR_PAD_LEFT);
        $formattedSeconds = str_pad($seconds, 2, "0", STR_PAD_LEFT);

        // Concatenate the formatted components
        $formattedDuration = $formattedHours . ':' . $formattedMinutes . ':' . $formattedSeconds;

        return $formattedDuration;
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];
    public function archive_recording()
    {
        return $this->hasOne(ArchiveRecording::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }
}
