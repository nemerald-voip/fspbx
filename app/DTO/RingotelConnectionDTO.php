<?php
namespace App\DTO;

class RingotelConnectionDTO
{
    public $accountId;
    public $address;
    public $created;
    public $domain;
    public $name;
    public $id;
    public $status;
    public $provision;

    public function __construct(array $data)
    {
        $this->accountId = $data['accountid'];
        $this->address = $data['address'];
        $this->created = $data['created'];
        $this->domain = $data['domain'];
        $this->name = $data['name'];
        $this->id = $data['id'];
        $this->status = $data['status'];
        $this->provision = $data['provision'] ?? [];
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }
}
