<?php
namespace App\DTO;

class PolycomOrganizationDTO implements OrganizationDTOInterface
{
    public ?string $id;
    public ?string $name;
    public bool $enabled;
    public object|array $template;

    private const DEFAULT_PROVISIONING = [
        'server' => [
            'address' => null,
            'username' => null,
            'password' => null,
        ],
        'polling' => false,
        'quickSetup' => false,
    ];

    private const DEFAULT_DHCP = [
        'bootServerOption' => null,
        'option60Type' => null,
    ];

    private const DEFAULT_SOFTWARE = [
        'version' => null,
    ];

    private const DEFAULT_LOCALIZATION = [
        'language' => null,
    ];

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->enabled = $data['enabled'] ?? false;
        $this->template = $this->buildTemplate($data['template'] ?? []);
    }

    private function buildTemplate(array $templateData): object
    {
        return (object) [
            'software' => $this->buildSoftware($templateData['software'] ?? []),
            'provisioning' => $this->buildProvisioning($templateData['provisioning'] ?? []),
            'dhcp' => $this->buildDhcp($templateData['dhcp'] ?? []),
            'localization' => $this->buildLocalization($templateData['localization'] ?? []),
        ];
    }

    private function buildSoftware(array $softwareData): object
    {
        $software = array_merge(self::DEFAULT_SOFTWARE, $softwareData);

        return (object) [
            'version' => $software['version'],
        ];
    }

    private function buildProvisioning(array $provisioningData): object
    {
        $provisioning = array_merge(self::DEFAULT_PROVISIONING, $provisioningData);

        return (object) [
            'server' => (object) [
                'address' => $provisioning['server']['address'],
                'username' => $provisioning['server']['username'],
                'password' => $provisioning['server']['password'],
            ],
            'polling' => $provisioning['polling'],
            'quickSetup' => $provisioning['quickSetup'],
        ];
    }

    private function buildDhcp(array $dhcpData): object
    {
        $dhcp = array_merge(self::DEFAULT_DHCP, $dhcpData);

        return (object) [
            'bootServerOption' => $dhcp['bootServerOption'],
            'option60Type' => $dhcp['option60Type'],
        ];
    }

    private function buildLocalization(array $localizationData): object
    {
        $localization = array_merge(self::DEFAULT_LOCALIZATION, $localizationData);

        return (object) [
            'language' => $localization['language'],
        ];
    }

    public static function fromArray(array $data): OrganizationDTOInterface
    {
        return new self($data);
    }

    public function __toString(): string
    {
        return json_encode(get_object_vars($this), JSON_PRETTY_PRINT);
    }
}
