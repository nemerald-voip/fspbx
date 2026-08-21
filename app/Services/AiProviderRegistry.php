<?php

namespace App\Services;

use App\Contracts\AiProviderClient;
use InvalidArgumentException;

class AiProviderRegistry
{
    /** @var array<string, AiProviderClient> */
    private array $clients;

    public function __construct(RetellApiClient $retell)
    {
        $this->clients = [
            $retell->provider() => $retell,
        ];
    }

    public function client(string $provider): AiProviderClient
    {
        $provider = strtolower(trim($provider));

        return $this->clients[$provider]
            ?? throw new InvalidArgumentException("Unsupported AI provider: {$provider}");
    }

    public function supports(string $provider): bool
    {
        return isset($this->clients[strtolower(trim($provider))]);
    }

    public function names(): array
    {
        return array_keys($this->clients);
    }

    public function options(): array
    {
        return collect($this->names())
            ->map(fn (string $provider) => [
                'value' => $provider,
                'label' => match ($provider) {
                    'retell' => 'Retell',
                    default => ucfirst($provider),
                },
            ])->all();
    }
}
