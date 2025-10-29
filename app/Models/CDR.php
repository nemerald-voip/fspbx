<?php

namespace App\Models;

use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
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

    protected $appends = [
        'created_at_formatted',
        'caller_id_number_formatted',
        'caller_destination_formatted',
        'destination_number_formatted',
        'start_date',
        'start_time',
        'duration_formatted',
        'billsec_formatted',
        'waitsec_formatted',
        'call_disposition',
        'cc_result',
        // Add more as needed...
    ];

    public function getCreatedAtFormattedAttribute()
    {
        if (!$this->created_at || !$this->domain_uuid) return null;
        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::parse($this->created_at)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

    public function getCallerIdNumberFormattedAttribute()
    {
        return $this->caller_id_number ? formatPhoneNumber($this->caller_id_number) : null;
    }

    public function getCallerDestinationFormattedAttribute()
    {
        return $this->caller_destination ? formatPhoneNumber($this->caller_destination) : null;
    }

    public function getDestinationNumberFormattedAttribute()
    {
        return $this->destination_number ? formatPhoneNumber($this->destination_number) : null;
    }

    public function getStartDateAttribute()
    {
        if (!$this->start_epoch || !$this->domain_uuid) return null;
        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::createFromTimestamp($this->start_epoch, 'UTC')->setTimezone($timeZone)->format('M d, Y');
    }

    public function getStartTimeAttribute()
    {
        if (!$this->start_epoch || !$this->domain_uuid) return null;
        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::createFromTimestamp($this->start_epoch, 'UTC')->setTimezone($timeZone)->format('g:i:s A');
    }

    public function getDurationFormattedAttribute()
    {
        return $this->duration ? $this->getFormattedDuration($this->duration) : null;
    }

    public function getBillsecFormattedAttribute()
    {
        return $this->billsec ? $this->getFormattedDuration($this->billsec) : null;
    }

    public function getWaitsecFormattedAttribute()
    {
        if ($this->start_epoch && $this->answer_epoch) {
            return $this->getFormattedDuration($this->answer_epoch - $this->start_epoch);
        }
        return null;
    }

    public function getCallDispositionAttribute()
    {
        $dispositions = [
            'send_bye'    => 'The recipient hung up.',
            'recv_bye'    => 'The caller hung up.',
            'send_refuse' => 'The call was refused by the recipient (e.g., busy or unavailable).',
            'recv_refuse' => 'The call was refused by the recipient (e.g., busy or unavailable).',
            'send_cancel' => 'The call was canceled before it was answered.',
            'recv_cancel' => 'The call was canceled before it was answered.',
        ];

        if ($this->sip_hangup_disposition && $this->direction) {
            return $dispositions[$this->sip_hangup_disposition] ?? 'Unknown disposition.';
        }

        // When `sip_hangup_disposition` is null and `hangup_cause` is "ORIGINATOR_CANCEL",
        // but only if `call_disposition` hasn't been set yet
        if (is_null($this->sip_hangup_disposition) && $this->hangup_cause == "ORIGINATOR_CANCEL") {
            return 'The call was canceled before it was answered.';
        }

        // When `sip_hangup_disposition` is null and `hangup_cause` is "LOSE_RACE",
        // but only if `call_disposition` hasn't been set yet
        if (is_null($this->sip_hangup_disposition) && $this->hangup_cause == "LOSE_RACE") {
            return 'The call was answered somewhere else.';
        }

        return null;
    }


    public function getCcResultAttribute()
    {
        if ($this->cc_cause == 'answered') {
            return 'Answered';
        }

        if ($this->cc_cause == 'cancel') {
            switch ($this->cc_cancel_reason) {
                case 'NONE':
                    return "No specific reason";
                case 'NO_AGENT_TIMEOUT':
                    return "No agents in queue";
                case 'BREAK_OUT':
                    return "Abandoned";
                case 'EXIT_WITH_KEY':
                    return "The caller pressed the exit key";
                case 'TIMEOUT':
                    return "Queue timeout reached";
            }
        }
        return null;
    }

    public function getStatusAttribute($value)
    {
        // 1. Missed call condition
        $status = $value;

        if ($this->voicemail_message == false && $this->missed_call == true && $this->hangup_cause == "NORMAL_CLEARING") {
            $status = "missed call";
        }

        // 2. Abandoned call upgrades missed call
        if (
            isset($this->cc_cancel_reason) &&
            isset($this->cc_cause) &&
            $status === "missed call" &&
            $this->cc_cancel_reason == "BREAK_OUT" &&
            $this->cc_cause == "cancel"
        ) {
            $status = "abandoned";
        }

        return $status;
    }

    public function getFormattedDuration($value)
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
    public function archive_recording()
    {
        return $this->hasOne(ArchiveRecording::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }

    /**
     * Get domain that this model belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get domain that this model belongs to 
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
    }

    public function callTranscription()
    {
        return $this->hasOne(CallTranscription::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }

    public function formatPhoneNumber($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $number_formatted = $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } else {
                $number_formatted = $value;
            }
        } catch (NumberParseException $e) {
            // Do nothing and leave the numbner as is
            $number_formatted = $value;
        }

        return $number_formatted;
    }

    public function relatedQueueCalls()
    {
        return $this->hasMany(CDR::class, 'cc_member_session_uuid', 'xml_cdr_uuid');
    }

    public function relatedRingGroupCalls()
    {
        return $this->hasMany(CDR::class, 'originating_leg_uuid', 'xml_cdr_uuid');
    }
}
