<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conferences extends Model
{
    use HasFactory;

    protected $table = "v_conferences";

    public $timestamps = false;

    protected $primaryKey = 'conference_uuid';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'conference_uuid',
        'dialplan_uuid',
        'conference_name',
        'conference_extension',
        'conference_pin_number',
        'conference_profile',
        'conference_email_address',
        'conference_account_code',
        'conference_flags',
        'conference_order',
        'conference_description',
        'conference_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan(): BelongsTo
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        // Conferences will have extensions in the range between 9350 and 9399 by default.
        $rangeStart = 9350;
        $rangeEnd = 9399;
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
                    ->pluck('conference_extension')
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
