<?php

namespace App\Models;

use App\Jobs\SendZtpRequest;
use App\Models\DeviceLines;
use App\Services\CloudProvisioningService;
use App\Services\Interfaces\ZtpProviderInterface;
use App\Services\PolycomZtpProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
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

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            /** @var Devices $model */
            // Remove attributes before saving to database
            unset(
                $model->device_address_formatted,
                $model->destroy_route,
                $model->send_notify_path
            );
        });

        static::retrieved(function ($model) {
            if ($model->device_address) {
                $model->device_address_formatted = $model->formatMacAddress($model->device_address);
            }
            $model->destroy_route = route('devices.destroy', $model);
            return $model;
        });

        static::deleted(function ($model) {
            /** @var Devices $model */
            // If device is deleted
            // TODO: better if we would know that the device was provisioned before sending deregister request
            $model->deregisterOnZtp();
        });
    }

    private function formatMacAddress(string $macAddress, $uppercase = true): string
    {
        $macAddress = ($uppercase) ? strtoupper($macAddress) : strtolower($macAddress);
        return implode(":", str_split($macAddress, 2));
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

    public function registerOnZtp(): void
    {
        try {
            // Check if the device has a supported cloud provider
            if ($this->hasSupportedCloudProvider()) {

                // Get the original 'device_address' value before it was updated
                /*$originalDeviceAddress = $this->getOriginal('device_address');

                logger('Device address has changed:', [
                    'old' => $originalDeviceAddress,
                    'new' => $this->device_address
                ]);

                // Check if the original device address is not empty and has changed
                if (!empty($originalDeviceAddress) && ($originalDeviceAddress != $this->device_address)) {

                    // Try to send a request to delete the old device address on ZTP
                    try {
                        SendZtpRequest::dispatch(
                            SendZtpRequest::ACTION_DELETE,
                            $this->device_vendor,
                            $originalDeviceAddress,
                        )->onQueue('ztp');
                    } catch (\Exception $e) {
                        // Log any exception that occurs during the deletion process
                        logger($e);
                    }
                }*/

                // Send a request to create or update the new device address on ZTP
                SendZtpRequest::dispatch(
                    SendZtpRequest::ACTION_CREATE,
                    $this->device_vendor,
                    $this->device_address,
                    $this->getCloudProviderOrganisationId()
                )->onQueue('ztp');
            }
        } catch (\Exception $e) {
            // Log any exception that occurs during the creation process
            logger($e);
        }
    }

    public function deregisterOnZtp(): void
    {
        try {
            // Check if the device has a supported cloud provider
            if ($this->hasSupportedCloudProvider()) {

                // Send a request to delete the device from the ZTP system using the current device address
                SendZtpRequest::dispatch(
                    SendZtpRequest::ACTION_DELETE,
                    $this->device_vendor,
                    $this->device_address,
                )->onQueue('ztp');
            }
        } catch (\Exception $e) {
            // Log any exception that occurs during the deregistration process
            logger($e);
        }
    }

    /**
     * @return bool
     */
    public function hasSupportedCloudProvider(): bool
    {
        return in_array($this->device_vendor, $this->supportedCloudProviders);
    }

    /**
     * @throws \Exception
     */
    public function getCloudProvider(): ZtpProviderInterface
    {
        // TODO: probably here we should prevent throw ingException if the provider isn't found
        return match ($this->device_vendor) {
            'polycom' => new PolycomZtpProvider(),
            //'yealink' => new YealinkZTPApiProvider(),
            default => throw new \Exception("Unsupported provider"),
        };
    }

    /**
     * @throws \Exception
     */
    public function getCloudProviderOrganisationId(): string
    {
        $domainSettings = DomainSettings::where('domain_uuid', Session::get('domain_uuid'))
            ->where('domain_setting_category', 'cloud provision');

        $domainSettings = match ($this->device_vendor) {
            'polycom' => $domainSettings->where('domain_setting_subcategory', 'polycom_ztp_profile_id'),
            //'yealink' => $domainSettings->where('domain_setting_subcategory', 'yealink_ztp_profile_id'),
            default => throw new \Exception("Unsupported provider"),
        };

        if ($domainSettings->count() == 0) {
            throw new \Exception("Organisation ID not found");
        }

        $orgId = $domainSettings->value('domain_setting_value');

        if (empty($orgId)) {
            throw new \Exception("Organisation ID is empty");
        }

        return $orgId;
    }
}
