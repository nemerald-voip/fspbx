<?php
namespace App\DTO;

class PolycomOrganizationDTO implements OrganizationDTOInterface
{
    public ?string $id;
    public ?string $name;
    public bool $enabled;
    public object $template;
    public ?object $custom = null;

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

    private const DEFAULT_CUSTOM = [
        'ucs' => null,
        'obi' => null,
    ];

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->enabled = $data['enabled'] ?? false;
        $this->template = $this->buildTemplate($data['template'] ?? []);
        $this->custom = (object) array_merge(self::DEFAULT_CUSTOM, $data['custom'] ?? []);

    }

    private function buildTemplate(array $templateData): object
    {
        return (object) [
            'software'      => $this->buildSoftware($templateData['software'] ?? []),
            'provisioning'  => $this->buildProvisioning($templateData['provisioning'] ?? []),
            'dhcp'          => $this->buildDhcp($templateData['dhcp'] ?? []),
            'localization'  => $this->buildLocalization($templateData['localization'] ?? []),
        ];
    }

    private function buildSoftware(array $softwareData): object
    {
        return (object) array_merge(self::DEFAULT_SOFTWARE, $softwareData);
    }

    private function buildProvisioning(array $provisioningData): object
    {
        // Ensure server is always an array and defaults are applied per key
        $server = array_merge(self::DEFAULT_PROVISIONING['server'], $provisioningData['server'] ?? []);
        $polling = $provisioningData['polling'] ?? self::DEFAULT_PROVISIONING['polling'];
        $quickSetup = $provisioningData['quickSetup'] ?? self::DEFAULT_PROVISIONING['quickSetup'];

        return (object) [
            'server'      => (object) $server,
            'polling'     => $polling,
            'quickSetup'  => $quickSetup,
        ];
    }

    private function buildDhcp(array $dhcpData): object
    {
        return (object) array_merge(self::DEFAULT_DHCP, $dhcpData);
    }

    private function buildLocalization(array $localizationData): object
    {
        return (object) array_merge(self::DEFAULT_LOCALIZATION, $localizationData);
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
