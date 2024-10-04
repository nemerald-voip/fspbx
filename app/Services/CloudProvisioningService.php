<?php

namespace App\Services;

use App\Models\DomainSettings;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Support\Facades\Session;

class CloudProvisioningService
{
    protected string $domainUuid;

    protected array $supportedProviders = [
        'polycom'
    ];

    public function __construct()
    {
        $this->domainUuid = Session::get('domain_uuid');
    }

    public function isSupportedProvider(string $provider): bool
    {
        return in_array($provider, $this->supportedProviders);
    }

    public function getCloudProvider(string $provider): ZtpProviderInterface
    {
        // TODO: probably here we should not throw Exception if provider isn't found
        return match ($provider) {
            'polycom' => new PolycomZtpProvider(),
            //'yealink' => new YealinkZTPApiProvider(),
            default => throw new \Exception("Unsupported provider"),
        };
    }

    /**
     * @string $provider
     * @throws \Exception
     */
    public function getCloudProviderOrganisationId(string $provider): string
    {
        $domainSettings = DomainSettings::where('domain_uuid', $this->domainUuid)
            ->where('domain_setting_category', 'cloud provision');

        $domainSettings = match ($provider) {
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
