<?php

namespace App\Services;

use Illuminate\Support\Str;

class TigerTmsSiteMapper
{
    public function inbound(?string $site): ?string
    {
        $site = trim((string) $site);

        if ($site === '') {
            return null;
        }

        if ($site === (string) config('tigertms.test_site_id', '001')) {
            return strtolower((string) config('tigertms.test_domain_uuid'));
        }

        return Str::isUuid($site) ? strtolower($site) : null;
    }

    public function outbound(string $domainUuid): string
    {
        $domainUuid = strtolower(trim($domainUuid));

        if ($domainUuid === strtolower((string) config('tigertms.test_domain_uuid'))) {
            return (string) config('tigertms.test_site_id', '001');
        }

        return $domainUuid;
    }
}
