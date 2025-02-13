<?php

namespace App\Services\Interfaces;

interface ZtpProviderInterface {
    public function getApiToken(): string;
    public function getProviderName(): string;
    public function getDevices(array $addresses = []): array;
    public function getDevice(string $id): array;
    public function createDevice(string $id, string $orgId): array;
    public function deleteDevice(string $id): array;
    public function getOrganisations(): array;
    public function createOrganisation(array $params): string;
    public function getOrganisation(string $id): string;
    public function updateOrganisation(string $id, array $params): string;
    public function deleteOrganisation(string $id): string;
}
