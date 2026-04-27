<?php

namespace App\Services;

use App\Models\CallFlows;
use Illuminate\Validation\ValidationException;

class CallFlowService
{
    public function buildSaveData(array $validated, ?CallFlows $callFlow, string $domainName): array
    {
        $destination = $this->buildDestinationAction(
            $validated['call_flow_action'] ?? null,
            $validated['call_flow_target'] ?? null,
            $validated['call_flow_destination'] ?? null,
            true,
            'call_flow_target',
            $domainName
        );

        if (!$destination) {
            throw ValidationException::withMessages([
                'call_flow_action' => ['Choose a default destination.'],
            ]);
        }

        $alternateDestination = $this->buildDestinationAction(
            $validated['call_flow_alternate_action'] ?? null,
            $validated['call_flow_alternate_target'] ?? null,
            $validated['call_flow_alternate_destination'] ?? null,
            false,
            'call_flow_alternate_target',
            $domainName
        );

        $validated['call_flow_app'] = $destination['app'];
        $validated['call_flow_data'] = $destination['data'];
        $validated['call_flow_alternate_app'] = $alternateDestination['app'] ?? null;
        $validated['call_flow_alternate_data'] = $alternateDestination['data'] ?? null;
        $validated['call_flow_context'] = $callFlow?->call_flow_context ?: $domainName;
        $validated['call_flow_group'] = filled($validated['call_flow_group'] ?? null)
            ? trim($validated['call_flow_group'])
            : null;

        return $validated;
    }

    private function buildDestinationAction(
        ?string $action,
        mixed $target,
        ?string $legacyDestination,
        bool $required,
        string $targetErrorKey,
        string $domainName
    ): ?array {
        if (filled($action)) {
            return $this->buildRoutingDestinationAction($action, $target, $targetErrorKey, $domainName);
        }

        return $this->splitDestination($legacyDestination, $required);
    }

    private function buildRoutingDestinationAction(string $action, mixed $target, string $targetErrorKey, string $domainName): array
    {
        return match ($action) {
            'check_voicemail' => [
                'app' => 'transfer',
                'data' => "*98 XML {$domainName}",
            ],
            'company_directory' => [
                'app' => 'transfer',
                'data' => "*411 XML {$domainName}",
            ],
            'hangup' => [
                'app' => 'hangup',
                'data' => 'NORMAL_CLEARING',
            ],
            default => $this->buildRoutingDestinationWithTarget($action, $target, $targetErrorKey, $domainName),
        };
    }

    private function buildRoutingDestinationWithTarget(
        string $action,
        mixed $target,
        string $targetErrorKey,
        string $domainName
    ): array {
        $target = $this->routingTargetValue($target);

        if (!filled($target)) {
            throw ValidationException::withMessages([
                $targetErrorKey => ['Choose a destination.'],
            ]);
        }

        return match ($action) {
            'recordings' => [
                'app' => 'lua',
                'data' => 'streamfile.lua ' . $target,
            ],
            'voicemails' => [
                'app' => 'transfer',
                'data' => "*99{$target} XML {$domainName}",
            ],
            default => [
                'app' => 'transfer',
                'data' => "{$target} XML {$domainName}",
            ],
        };
    }

    private function splitDestination(?string $destination, bool $requireData): ?array
    {
        if (!filled($destination) || !str_contains($destination, ':')) {
            return $requireData ? null : [];
        }

        [$app, $data] = explode(':', $destination, 2);
        $app = trim($app);
        $data = trim($data);

        if ($app === '' || ($requireData && $data === '')) {
            return null;
        }

        return [
            'app' => $app,
            'data' => $data,
        ];
    }

    private function routingTargetValue(mixed $target): ?string
    {
        if (is_array($target)) {
            $target = $target['extension'] ?? $target['value'] ?? null;
        }

        $target = trim((string) $target);

        return $target !== '' ? $target : null;
    }
}
