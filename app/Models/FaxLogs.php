<?php

namespace App\Models;

use Carbon\Carbon;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class FaxLogs extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax_logs";

    public $timestamps = false;

    protected $primaryKey = 'fax_log_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = [
        'fax_date_formatted',
        'source_formatted',
        'destination_formatted',
    ];

    protected $casts = [
        'fax_epoch' => 'integer',
    ];


    public function faxFile()
    {
        return $this->hasOne(FaxFiles::class, 'fax_file_uuid', 'fax_log_uuid');
    }

    public function getFaxDateFormattedAttribute()
    {
        if (!$this->fax_epoch || !$this->domain_uuid) {
            return null;
        }

        $timeZone = get_local_time_zone($this->domain_uuid);

        return Carbon::createFromTimestamp($this->fax_epoch, 'UTC')
            ->setTimezone($timeZone)
            ->format('g:i:s A M d, Y');
    }

    public function getSourceFormattedAttribute()
    {
        if (empty($this->source)) {
            return null;
        }

        $countryCode = $this->phoneCountryCode();

        return $countryCode
            ? formatPhoneNumber($this->source, $countryCode, PhoneNumberFormat::NATIONAL)
            : $this->source;
    }

    public function getDestinationFormattedAttribute()
    {
        if (empty($this->destination)) {
            return null;
        }

        $countryCode = $this->phoneCountryCode();

        return $countryCode
            ? formatPhoneNumber($this->destination, $countryCode, PhoneNumberFormat::NATIONAL)
            : $this->destination;
    }

    private function phoneCountryCode(): ?string
    {
        return $this->domain_uuid
            ? get_domain_setting('country', $this->domain_uuid)
            : null;
    }

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }

    public function outboundFax()
    {
        return $this->belongsTo(OutboundFax::class, 'outbound_fax_uuid', 'outbound_fax_uuid');
    }
}
