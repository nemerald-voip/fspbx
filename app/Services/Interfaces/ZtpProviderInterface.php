<?php

namespace App\Services\Interfaces;

use \Illuminate\Support\Collection;

interface ZtpProviderInterface {
    public function getApiToken(): string;
    public function getProviderName(): string;
    public function getDevices(array $addresses = []): array;
    public function getDevice(string $id): array;
    public function createDevice(string $id, string $orgId): array;
    public function deleteDevice(string $id): array;
    public function getOrganizations(): Collection;
    public function createOrganization(array $params): string;
    public function getOrganization(string $id): string;
    public function updateOrganization(string $id, array $params): string;
    public function deleteOrganization(string $id): string;
}
