<?php

namespace App\Models;

use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    /**
     * Cache the results to prevent repeated processing of the action details
     */
    protected array $exitOptionDetailsCache = [];

    /**
     * Compute and assign the exit options based on the IVR Menu exit application + data
     */
    protected function exitOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->computeExitOptionDetails()
        )->shouldCache();
    }

    protected function computeExitOptionDetails(): array
    {
        if (!empty($this->exitOptionDetailsCache)) {
            return $this->exitOptionDetailsCache;
        }

        if (empty($this->ivr_menu_exit_app) && empty($this->ivr_menu_exit_data)) {
            return $this->exitOptionDetailsCache = [
                'type' => null,
                'extension' => null,
                'option' => null,
                'name' => null
            ];
        }

        $service = new CallRoutingOptionsService($this->domain_uuid ?? session('domain_uuid'));

        // Combine action and data to feed into the CallRoutingOptionsService
        $actionString = trim("{$this->ivr_menu_exit_app} {$this->ivr_menu_exit_data}");

        return $this->exitOptionDetailsCache = $service->reverseEngineerIVROption($actionString) ?? [
            'type' => null,
            'extension' => null,
            'option' => null,
            'name' => null
        ];
    }

    public function getExitActionAttribute(): ?string
    {
        return $this->exit_option_details['type'] ?? null;
    }

    public function getExitTargetUuidAttribute(): ?string
    {
        return $this->exit_option_details['option'] ?? null;
    }

    public function getExitTargetExtensionAttribute(): ?string
    {
        return $this->exit_option_details['extension'] ?? null;
    }

    public function getExitTargetNameAttribute(): ?string
    {
        return $this->exit_option_details['name'] ?? null;
    }


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
            ->map(fn($value) => (string) $value)
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
