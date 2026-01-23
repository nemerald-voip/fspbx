<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\DeviceLines;
use App\Models\DeviceCloudProvisioning;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devices extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_devices";

    public $timestamps = false;

    protected $primaryKey = 'device_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'device_profile_uuid',
        'device_address',
        'serial_number',
        'device_label',
        'device_vendor',
        'device_model',
        'device_firmware_version',
        'device_enabled',
        'device_enabled_date',
        'device_user_uuid',
        'device_username',
        'device_password',
        'device_uuid_alternate',
        'device_description',
        'device_provisioned_date',
        'device_provisioned_method',
        'device_provisioned_ip',
        'device_provisioned_agent',
        'device_template',
        'device_template_uuid',
        'device_user_uuid',
        'device_username'
    ];

    protected $appends = ['device_address_formatted', 'device_provisioned_date_formatted'];

    public function getDeviceProvisionedDateFormattedAttribute()
    {
        if (!$this->device_provisioned_date || !$this->domain_uuid) {
            return null;
        }
        $timeZone = get_local_time_zone($this->domain_uuid);
        return Carbon::parse($this->device_provisioned_date)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

    public function getDeviceAddressFormattedAttribute()
    {
        return $this->device_address ? formatMacAddress($this->device_address) : null;
    }

    /**
     * Get the Device Lines objects associated with this device.
     *  returns Eloquent Object
     */
    public function lines()
    {
        return $this->hasMany(DeviceLines::class, 'device_uuid', 'device_uuid');
    }

    /**
     * Get the Legacy Device Keys objects associated with this device.
     *  returns Eloquent Object
     */
    public function legacy_keys(): HasMany
    {
        return $this->hasMany(LegacyDeviceKey::class, 'device_uuid', 'device_uuid');
    }

    /**
     * Get the Device Keys objects associated with this device.
     *  returns Eloquent Object
     */
    public function keys(): HasMany
    {
        return $this->hasMany(DeviceKey::class, 'device_uuid', 'device_uuid');
    }

    /**
     * Get the Device Profile object associated with this device.
     *  returns Eloquent Object
     */
    public function profile(): BelongsTo
    {
        // Device has FK device_profile_uuid → it *belongsTo* a DeviceProfile
        return $this->belongsTo(DeviceProfile::class, 'device_profile_uuid', 'device_profile_uuid');
    }

    public function cloudProvisioning()
    {
        return $this->hasOne(DeviceCloudProvisioning::class, 'device_uuid', 'device_uuid');
    }

    /**
     * Get the Extension that the device is assigned for.
     * @return mixed|null
     */
    public function extension()
    {
        return ($this->lines()->first() && $this->lines()->first()->extension()) ? $this->lines()->first()->extension() : null;
    }

    /**
     * Get domain that this message settings belongs to
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function template()
    {
        return $this->hasOne(
            \App\Models\ProvisioningTemplate::class,
            'template_uuid',
            'device_template_uuid'
        );
    }

    /**
     * @throws \Exception
     */
    public function getCloudProviderOrganizationId(): string
    {
        $domainSettings = DomainSettings::where('domain_uuid', $this->domain_uuid /*Session::get('domain_uuid')*/)
            ->where('domain_setting_category', 'cloud provision');

        $domainSettings = match ($this->device_vendor) {
            'polycom' => $domainSettings->where('domain_setting_subcategory', 'polycom_ztp_profile_id'),
            //'yealink' => $domainSettings->where('domain_setting_subcategory', 'yealink_ztp_profile_id'),
            default => throw new \Exception("Unsupported provider"),
        };

        if ($domainSettings->count() == 0) {
            throw new \Exception("Organization ID not found");
        }

        $orgId = $domainSettings->value('domain_setting_value');

        if (empty($orgId)) {
            throw new \Exception("Organization ID is empty");
        }

        return $orgId;
    }

    public function settings()
    {
        return $this->hasMany(DeviceSettings::class, 'device_uuid', 'device_uuid');
    }
}
