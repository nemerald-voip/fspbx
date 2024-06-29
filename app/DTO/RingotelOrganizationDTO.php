<?php
namespace App\DTO;

class RingotelOrganizationDTO
{
    public $created;
    public $domain;
    public $name;
    public $options;
    public $packageid;
    public $id;
    public $region;
    public $params;
    public $status;

    public function __construct(array $data)
    {
        $this->created = $data['created'];
        $this->domain = $data['domain'];
        $this->name = $data['name'];
        $this->options = $data['options'];
        $this->packageid = $data['packageid'];
        $this->id = $data['id'];
        $this->region = $data['region'];
        $this->params = $data['params'] ?? []; 
        $this->status = $data['status'];
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }
}
