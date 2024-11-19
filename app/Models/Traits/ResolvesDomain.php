<?php

namespace App\Models\Traits;

trait ResolvesDomain
{
    protected static ?string $runtimeDomainUuid = null;

    public static function setRuntimeDomainUuid(string $domainUuid): void
    {
        self::$runtimeDomainUuid = $domainUuid;
    }

    public static function getRuntimeDomainUuid(): ?string
    {
        return self::$runtimeDomainUuid;
    }

    public static function clearRuntimeDomainUuid(): void
    {
        self::$runtimeDomainUuid = null;
    }
}
