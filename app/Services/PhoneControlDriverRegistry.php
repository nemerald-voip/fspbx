<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class PhoneControlDriverRegistry
{
    /** @var array<string, PhoneControlDriver> */
    private array $drivers;

    public function __construct(
        YealinkPhoneControlDriver $yealink,
        SnomPhoneControlDriver $snom,
        PolyPhoneControlDriver $poly,
        PbxCallControl $pbx
    ) {
        $this->drivers = [];
        $this->register($yealink);
        $this->register($snom);
        $this->register($poly);
        // Order matters: the specific match must be registered before the
        // catch-all, since forAgent() returns the first driver that matches.
        $this->register(new GenericPhoneControlDriver($pbx, 'grandstream', 'Grandstream', ['grandstream']));
        $this->register(new GenericPhoneControlDriver($pbx, 'generic', 'Generic (PBX-assisted)', []));
    }

    public function vendors(): array
    {
        return array_keys($this->drivers);
    }

    public function forVendor(string $vendor): PhoneControlDriver
    {
        $vendor = Str::lower(trim($vendor));
        $driver = $this->drivers[$vendor] ?? null;

        if (! $driver) {
            throw new RuntimeException(
                "Phone controls are not implemented for vendor [{$vendor}]. Supported vendors: "
                . implode(', ', $this->vendors()) . '.'
            );
        }

        return $driver;
    }

    public function forAgent(string $agent): ?PhoneControlDriver
    {
        foreach ($this->drivers as $driver) {
            if ($driver->matchesAgent($agent)) {
                return $driver;
            }
        }

        return null;
    }

    private function register(PhoneControlDriver $driver): void
    {
        $this->drivers[$driver->vendor()] = $driver;
    }
}
