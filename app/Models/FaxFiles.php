<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Traits\TraitUuid;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\FilterableByLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class FaxFiles extends Model
{
    use HasFactory, FilterableByLocation, TraitUuid;

    protected $table = "v_fax_files";

    public $timestamps = false;

    protected $primaryKey = 'fax_file_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = [
        'fax_date_formatted',
        'fax_caller_id_number_formatted',
    ];

    public function locations()
    {
        return $this->belongsToMany(
            Location::class,   // related
            'locationables',               // pivot
            'locationable_id',             // pivot FK -> this model’s local key below
            'location_uuid',               // pivot FK -> Location
            'fax_uuid',                    // local key on FaxQueues (not PK)
            'location_uuid'                // local key on Location
        )->wherePivot('locationable_type', Faxes::class);
    }


    public function getFaxDateFormattedAttribute()
    {
        if (!$this->fax_date || !$this->domain_uuid) {
            return null;
        }
        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::parse($this->fax_date)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

    public function getFaxCallerIdNumberFormattedAttribute()
    {
        return formatPhoneNumber($this->fax_caller_id_number, 'US', PhoneNumberFormat::NATIONAL);
    }

    /**
     * Get the domain to which this faxfile belongs
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }

    public function faxQueue()
    {
        return $this->belongsTo(FaxQueues::class, 'fax_file_path', 'fax_file');
    }
}
