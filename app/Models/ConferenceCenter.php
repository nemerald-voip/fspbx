<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceCenter extends Model
{
    use HasFactory;

    protected $table = "v_conference_centers";

    public $timestamps = false;

    protected $primaryKey = 'conference_center_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'conference_center_uuid',
        'dialplan_uuid',
        'conference_center_name',
        'conference_center_extension',
        'conference_center_greeting',
        'conference_center_pin_length',
        'conference_center_enabled',
        'conference_center_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan()
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        // Conference Centers will have extensions in the range between 9300 and 9349 by default.
        $rangeStart = 9300;
        $rangeEnd = 9349;
        $domainUuid = session('domain_uuid');

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
            ->merge(
                self::where('domain_uuid', $domainUuid)
                    ->pluck('conference_center_extension')
            )
            ->filter(fn ($value) => ctype_digit((string) $value))
            ->map(fn ($value) => (string) (int) $value)
            ->unique()
            ->flip();

        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (! isset($usedExtensions[(string) $ext])) {
                return (string) $ext;
            }
        }

        return null;
    }
}
