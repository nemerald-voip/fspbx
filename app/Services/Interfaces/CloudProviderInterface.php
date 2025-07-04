<?php

namespace App\Services\Interfaces;

use App\Models\Devices;
use \Illuminate\Support\Collection;
use App\DTO\OrganizationDTOInterface;

interface CloudProviderInterface {
    public function getApiToken();
    public function getProviderName(): string;
    public function getDevices(int $limit, string $cursor): array;
    public function getDevice(string $id): array;
    public function createDevice(array $params): array;
    public function deleteDevice(array $params): array;
    public function getOrganizations(): Collection;
    public function createOrganization(array $params): string;
    public function getOrganization(string $id): OrganizationDTOInterface;
    public function updateOrganization(string $id, array $params): string;
    public function deleteOrganization(string $id): string;
    public static function getOrgIdByDomainUuid(string $domainUuid): mixed;
    /**
     * Return UI options/settings for this provider as a multidimensional array.
     * @return array
     */
    public static function getSettings(): array;
}
