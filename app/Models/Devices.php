<?php

namespace App\Models;

use App\Models\DeviceLines;
use App\Jobs\SendZtpRequest;
use App\Services\PolycomZtpProvider;
use App\Models\DeviceCloudProvisioning;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devices extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_devices";

    public $timestamps = false;

    protected $primaryKey = 'device_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected array $supportedCloudProviders = [
        'polycom'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'device_profile_uuid',
        'device_address',
        'device_label',
        'device_vendor',
        'device_model',
        'device_firmware_version',
        'device_enabled',
        'device_enabled_date',
        'device_template',
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
        'device_user_uuid',
        'device_username'
    ];

    protected $appends = ['device_address_formatted'];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->fill($attributes);
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
     * Get the Device Profile object associated with this device.
     *  returns Eloquent Object
     */
    public function profile()
    {
        return $this->hasOne(DeviceProfile::class, 'device_profile_uuid', 'device_profile_uuid');
    }

    public function cloudProvisioning(): \Illuminate\Database\Eloquent\Relations\HasOne
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

    private function setProvisioningPending(string $deviceVendor = null): void {
        $this->cloudProvisioningStatus()->updateOrInsert([
            'device_uuid' => $this->device_uuid,
        ], [
            'provider' => $this->getCloudProvider($deviceVendor)->getProviderName(),
            'status' => 'pending',
            'device_address' => $this->device_address
        ]);
    }

    public function registerOnZtp(): void
    {
        try {
            if ($this->canProvisionOnZtp()) {
                SendZtpRequest::dispatch(
                    SendZtpRequest::ACTION_CREATE,
                    $this->device_vendor,
                    $this->device_address,
                    $this->getCloudProviderOrganizationId()
                )->onQueue('ztp');
            }
        } catch (\Exception $exception) {
            $this->logProvisioningError('Error occurred during device registration on ZTP.', $exception);
        }
    }

    public function deregisterOnZtp(string $deviceAddress = null, string $deviceVendor = null, bool $forceRemove = false): void
    {
        try {
            if ($this->canProvisionOnZtp($deviceVendor)) {
                SendZtpRequest::dispatch(
                    SendZtpRequest::ACTION_DELETE,
                    $this->getOldOrCurrentValue($deviceVendor, $this->device_vendor),
                    $this->getOldOrCurrentValue($deviceAddress, $this->device_address),
                    null,
                    $forceRemove
                )->onQueue('ztp');
            }
        } catch (\Exception $exception) {
            $this->logProvisioningError('Error occurred during device de-registration on ZTP.', $exception);
        }
    }

    private function canProvisionOnZtp(?string $deviceVendor = null): bool
    {
        if ($this->hasSupportedCloudProvider($deviceVendor ?? $this->device_vendor)) {
            $this->setProvisioningPending($deviceVendor);
            return true;
        }
        return false;
    }

    private function getOldOrCurrentValue(?string $newValue, string $currentValue): string
    {
        return empty($newValue) ? $currentValue : $newValue;
    }

    private function logProvisioningError(string $message, \Exception $exception): void
    {
        logger($message);
        logger($exception->getMessage(), ['exception' => $exception]);
    }

    /**
     * @param  string|null  $deviceVendor
     * @return bool
     */
    public function hasSupportedCloudProvider(string $deviceVendor = null): bool
    {
        return in_array((empty($deviceVendor) ? $this->device_vendor : $deviceVendor), $this->supportedCloudProviders);
    }

    /**
     * @throws \Exception
     */
    public function getCloudProvider(string $deviceVendor = null): ZtpProviderInterface
    {
        // TODO: probably here we should prevent Exception if the provider isn't found
        return match ((empty($deviceVendor)) ? $this->device_vendor : $deviceVendor) {
            'polycom' => new PolycomZtpProvider(),
            //'yealink' => new YealinkZTPApiProvider(),
            default => throw new \Exception("Unsupported provider"),
        };
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
}
