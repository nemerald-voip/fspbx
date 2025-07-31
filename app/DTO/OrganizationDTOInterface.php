<?php
namespace App\DTO;

interface OrganizationDTOInterface
{
    public static function fromArray(array $data): self;
    public function __toString(): string;
}
