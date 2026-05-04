<?php

namespace App\Models;

use App\Models\FaxAllowedEmails;
use App\Models\FaxAllowedDomainNames;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\FilterableByLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Faxes extends Model
{
    use HasFactory, FilterableByLocation, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax";

    public $timestamps = false;

    protected $primaryKey = 'fax_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'domain_uuid',
        'fax_name',
        'fax_extension',
        'accountcode',
        'fax_destination_number',
        'fax_prefix',
        'fax_email',
        'fax_caller_id_name',
        'fax_caller_id_number',
        'fax_forward_number',
        'fax_toll_allow',
        'fax_send_channels',
        'fax_description'
    ];

    protected $appends = [
        'fax_caller_id_number_formatted',
        'fax_destination_formatted',
    ];

    /**
     * Get all of the locations for this fax.
     * The name 'locationable' is the key we defined in the pivot table.
     */
    public function locations()
    {
        return $this->morphToMany(
            Location::class,       // 1. Related model
            'locationable',        // 2. The morph name (e.g., locationable_type, locationable_id)
            'locationables',       // 3. The pivot table name
            'locationable_id',     // 4. The foreign key on the pivot table for this model (Faxes)
            'location_uuid',       // 5. The foreign key on the pivot table for the related model (Location)
            'fax_uuid',            // 6. The local key on this model (Faxes)
            'location_uuid'        // 7. The local key on the related model (Location)
        );
    }

    public function getFaxCallerIdNumberFormattedAttribute()
    {
        return formatPhoneNumber($this->fax_caller_id_number, 'US', PhoneNumberFormat::NATIONAL);
    }

    public function getFaxDestinationFormattedAttribute()
    {
        return formatPhoneNumber($this->fax_destination, 'US', PhoneNumberFormat::NATIONAL);
    }

    // private $domain
    public function dialplans()
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    /**
     * Get allowed email addresses associated with this fax.
     *  returns Eloqeunt Object
     */
    public function allowed_emails()
    {
        return $this->hasMany(FaxAllowedEmails::class, 'fax_uuid', 'fax_uuid');
    }

    /**
     * Get allowed email addresses associated with this fax.
     *  returns Eloqeunt Object
     */
    public function allowed_domain_names()
    {
        return $this->hasMany(FaxAllowedDomainNames::class, 'fax_uuid', 'fax_uuid');
    }

    /**
     * Get domain associated with this fax.
     *  returns Eloqeunt Object
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan()
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {
        // Fax Servers will have extensions in the range between 50000 and 50500 by default
        $rangeStart = 50000;
        $rangeEnd = 50500;

        $domainUuid = session('domain_uuid');

        // Fetch all used extensions from Dialplans, Voicemails, and Extensions
        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )
            ->unique();

        // Find the first available extension
        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains($ext)) {
                // This is your unique extension
                $uniqueExtension = $ext;
                break;
            }
        }

        if (isset($uniqueExtension)) {
            return (string) $uniqueExtension;
        }

        // Return null if unable to generate a unique sequence number
        return null;
    }
}
