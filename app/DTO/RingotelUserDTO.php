<?php

namespace App\DTO;

class RingotelUserDTO
{
    public $branchId;
    public $extension;
    public $trunkState;
    public $created;
    public $stime;
    public $devices;
    public $domain;
    public $name;
    public $authName;
    public $id;
    public $state;
    public $status;
    public $username;
    public $email;

    public function __construct(array $data)
    {
        $this->branchId = $data['branchid'];
        $this->extension = $data['extension'];
        $this->trunkState = $data['trunkstate'];
        $this->created = $data['created'];
        $this->stime = $data['stime'];
        $this->devices = $data['devs'] ?? [];
        $this->domain = $data['domain'];
        $this->name = $data['name'] ?? "";
        $this->authName = $data['authname'] ?? [];
        $this->id = $data['id'];
        $this->state = $data['state'];
        $this->status = $data['status'];
        $this->username = $data['username'];

        // Check if 'info' exists and extract 'email' if present
        $this->email = $data['info']['email'] ?? null;
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }

    public function __toString()
    {
        return json_encode(get_object_vars($this));
    }
}
