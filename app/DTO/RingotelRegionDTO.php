<?php

namespace App\DTO;

class RingotelRegionDTO
{
    public $id;
    public $name;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }
}
