<?php

namespace App\Services\Interfaces;

use App\DTO\OrganizationDTOInterface;
use \Illuminate\Support\Collection;

interface CloudProviderInterface {
    public function getApiToken();
    public function getProviderName(): string;
    public function getDevices(int $limit, string $cursor): array;
    public function getDevice(string $id): array;
    public function createDevice(string $id, string $orgId): array;
    public function deleteDevice(string $id): array;
    public function getOrganizations(): Collection;
    public function createOrganization(array $params): string;
    public function getOrganization(string $id): OrganizationDTOInterface;
    public function updateOrganization(string $id, array $params): string;
    public function deleteOrganization(string $id): string;
    public function getOrgIdByDomainUuid(string $domainUuid): mixed;
}
