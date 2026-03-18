<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IvrMenus extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_ivr_menus';

    public $timestamps = false;

    protected $primaryKey = 'ivr_menu_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'ivr_menu_name',
        'ivr_menu_extension',
        'ivr_menu_description',
        'ivr_menu_greet_long',
        'ivr_menu_enabled',
        'ivr_menu_digit_len',
        'ivr_menu_timeout',
        'ivr_menu_pin_number',
        'ivr_menu_ringback',
        'ivr_menu_invalid_sound',
        'ivr_menu_exit_sound',
        'ivr_menu_direct_dial',
        'ivr_menu_max_failures',
        'ivr_menu_max_timeouts',
        'ivr_menu_exit_app',
        'ivr_menu_exit_data',
        'ivr_menu_context',
        'ivr_menu_cid_prefix',
    ];

    public function options()
    {
        return $this->hasMany(IvrMenuOptions::class, 'ivr_menu_uuid', 'ivr_menu_uuid');
    }

    public function getId()
    {
        return $this->ivr_menu_extension;
    }

    public function getName()
    {
        return $this->ivr_menu_extension . ' - ' . $this->ivr_menu_name;
    }

    /**
     * Generates a unique sequence number.
     */
    public function generateUniqueSequenceNumber(): ?string
    {
        $rangeStart = 9150;
        $rangeEnd = 9199;
        $domainUuid = session('domain_uuid');

        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)->pluck('extension')
            )
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains((string) $ext)) {
                return (string) $ext;
            }
        }

        return null;
    }
}