<?php

namespace App\Services\Interfaces;

use \Illuminate\Support\Collection;
use App\DTO\OrganizationDTOInterface;

interface CloudProviderInterface {
    public function getApiToken();
    public function setApiToken(string $token): void; 
    public function getProviderName(): string;
    public function getDevices(int $limit, string $cursor): array;
    public function getDevice(string $id): array;
    public function createDevice(array $params): array;
    public function deleteDevice(array $params): array;
    public function getOrganizations(): Collection;
    public function createOrganization(array $params);
    public function getOrganization(string $id): OrganizationDTOInterface;
    public function updateOrganization(array $params);
    public function deleteOrganization(string $id);
    public static function getOrgIdByDomainUuid(string $domainUuid): mixed;
    /**
     * Return UI options/settings for this provider as a multidimensional array.
     * @return array
     */
    public static function getSettings(): array;
}
