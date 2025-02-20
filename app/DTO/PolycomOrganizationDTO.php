<?php
namespace App\DTO;

class PolycomOrganizationDTO implements OrganizationDTOInterface
{
    public ?string $id;
    public ?string $name;
    public bool $enabled;
    public object|array $template;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->enabled = $data['enabled'] ?? false;
        $this->template = $this->parseTemplate($data['template'] ?? []);
    }

    /**
     * Parse the "template" data structure from the JSON.
     *
     * @param array $template
     * @return object
     */
    private function parseTemplate(array $template): object
    {
        return (object) [
            'software' => $template['software'] ?? null,
            'provisioning' => isset($template['provisioning'])
                ? $this->parseProvisioning($template['provisioning'])
                : null,
            'dhcp' => $template['dhcp'] ?? null,
            'localization' => $template['localization'] ?? null,
        ];
    }

    /**
     * Parse the "provisioning" data structure.
     *
     * @param array $provisioning
     * @return object
     */
    private function parseProvisioning(array $provisioning): object
    {
        return (object) [
            'server' => (object) [
                'address' => $provisioning['server']['address'] ?? null,
                'username' => $provisioning['server']['username'] ?? null,
                'password' => $provisioning['server']['password'] ?? null,
            ],
            'polling' => $provisioning['polling'] ?? null,
            'quickSetup' => $provisioning['quickSetup'] ?? null,
        ];
    }

    /**
     * Creates a new instance of PolycomOrganizationDTO from an array of data.
     *
     * @param array $data
     * @return PolycomOrganizationDTO
     */
    public static function fromArray(array $data): OrganizationDTOInterface
    {
        return new self($data);
    }

    /**
     * Converts the DTO object into a JSON string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return json_encode(get_object_vars($this), JSON_PRETTY_PRINT);
    }
}
