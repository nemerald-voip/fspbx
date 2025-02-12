<?php

namespace App\Services\Interfaces;

interface ZtpProviderInterface {
    public function getProviderName(): string;
    public function getDevices(array $addresses = [], int $limit = 50): array;
    public function getDevice(string $deviceId): array;
    public function createDevice(string $deviceId, string $orgId): array;
    public function deleteDevice(string $deviceId): array;
    public function getOrganisations(): array;
    public function createOrganisation(array $params): string;
    public function getOrganisation(string $id): string;
    public function updateOrganisation(array $params): string;
    public function deleteOrganisation(string $id): string;
    //public function createDeviceOnQueue(string $deviceId, string $orgId): void;
    //public function deleteDeviceOnQueue(string $deviceId): void;
}
