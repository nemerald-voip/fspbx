<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;

class AgentDirectoryCacheService
{
    public function agentsChanged(array $agents): void
    {
        $keys = [];
        foreach ($agents as $agent) {
            $domainUuid = $agent['domain_uuid'] ?? null;
            $domain = Domain::where('domain_uuid', $domainUuid)->value('domain_name');
            // Contact is authoritative. Agent ID is only a diagnostic field.
            if (! $domain || ! preg_match('~^user/([^@/]+)@([^/]+)$~D', $agent['agent_contact'] ?? '', $match)
                || $match[2] !== $domain) {
                continue;
            }
            $keys[] = 'directory:'.$match[1].'@'.$domain;
            $extensions = Extensions::without('advSettings')->where('domain_uuid', $domainUuid)
                ->where(fn ($query) => $query->where('extension', $match[1])->orWhere('number_alias', $match[1]))
                ->get(['extension', 'number_alias', 'user_context', 'domain_uuid']);
            foreach ($extensions as $extension) {
                $keys = array_merge($keys, $this->extensionKeys($extension->getAttributes(), $domain));
            }
        }
        $this->clearAfterCommit($keys);
    }

    public function extensionsChanged(array $extensions): void
    {
        $keys = [];
        foreach ($extensions as $extension) {
            $domain = Domain::where('domain_uuid', $extension['domain_uuid'] ?? null)->value('domain_name');
            $keys = array_merge($keys, $this->extensionKeys($extension, $domain));
        }
        $this->clearAfterCommit($keys);
    }

    public function domainChanged(string $domainUuid, array $names): void
    {
        $keys = [];
        foreach (Extensions::without('advSettings')->where('domain_uuid', $domainUuid)->get(['extension', 'number_alias', 'user_context']) as $extension) {
            foreach (array_unique($names) as $name) {
                $keys = array_merge($keys, $this->extensionKeys($extension->getAttributes(), $name));
            }
        }
        $this->clearAfterCommit($keys);
    }

    private function extensionKeys(array $extension, ?string $domain): array
    {
        $keys = [];
        foreach (array_unique(array_filter([$domain, $extension['user_context'] ?? null])) as $context) {
            foreach (array_unique(array_filter([$extension['extension'] ?? null, $extension['number_alias'] ?? null])) as $number) {
                $keys[] = 'directory:'.$number.'@'.$context;
            }
        }
        return $keys;
    }

    protected function clearAfterCommit(array $keys): void
    {
        $keys = array_values(array_unique($keys));
        if ($keys) {
            DB::afterCommit(function () use ($keys) {
                foreach ($keys as $key) {
                    // These are exact directory keys, never cache wildcard expressions.
                    if (strpbrk($key, '*?[]\\/') !== false) {
                        logger()->warning('Skipped unsafe agent directory cache key');
                        continue;
                    }
                    FusionCache::clear($key);
                }
            });
        }
    }
}
