<?php

namespace App\Services\Interfaces;

interface ZtpProviderInterface {
    public function listDevices(int $limit = 50): array;
    public function getDevice(string $deviceId): array;
    public function createDevice(string $deviceId, string $orgId): array;
    public function deleteDevice(string $deviceId): array;
    //public function listOrganisations(int $limit = 50): string;
    //public function createOrganisation(string $name): string;
    //public function getOrganisation(string $id): string;
    //public function updateOrganisation(string $id, string $name): string;
    //public function deleteOrganisation(string $id): string;
}
