<?php

namespace App\DTO;

class YealinkRpsServerDTO implements OrganizationDTOInterface
{
    public ?string $id;
    public ?string $name;
    public bool $enabled = true;
    public object $template;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? $data['_id'] ?? null;
        $this->name = $data['serverName'] ?? null;
        $this->template = (object) [
            'provisioning' => (object) [
                'server' => (object) [
                    'address' => $data['url'] ?? null,
                    'username' => $data['authName'] ?? null,
                    'password' => null,
                ],
            ],
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
