<?php

namespace App\Services\Interfaces;

interface ZtpProviderInterface {
    public function listDevices(int $limit = 50): string;
    public function createDevice(string $id, string $profile): string;
    public function deleteDevice(string $id): string;
    public function listOrganisations(int $limit = 50): string;
    public function createOrganisation(string $name): string;
    public function getOrganisation(string $id): string;
    public function updateOrganisation(string $id, string $name): string;
    public function deleteOrganisation(string $id): string;
}
