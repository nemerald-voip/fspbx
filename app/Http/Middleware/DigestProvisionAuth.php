<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Provisioning\VendorRouter;
use App\Services\Provisioning\DomainFromRequest;
use App\Services\Provisioning\ProvisionCredsResolver;

class DigestProvisionAuth
{
    public function handle(Request $request, Closure $next)
    {
        // 1) Parse route pieces (support both new {id}.{ext} and legacy {path})
        [$id, $ext] = $this->extractIdAndExt($request);

        // 2) Build token from id (strip non-alnum, lowercase)
        $token       = VendorRouter::tokenFromId($id);
        $contentType = VendorRouter::contentTypeFromExt($ext);

        logger("id: " . $id);
        logger("id: " . $ext);
        logger("id: " . $contentType);


        // 3) Find device by token (MAC or serial)
        $device = VendorRouter::findDeviceByToken($token);
        logger("id: " . $device);

        // 4) Pick domain_uuid (prefer device → else host mapping)
        $domain_uuid = app(DomainFromRequest::class)->resolve($device, $request);

        // 5) Load creds for domain (fallback to default inside resolver). No credential caching.
        $creds = app(ProvisionCredsResolver::class)->forDomain($domain_uuid);
        if (!$creds) {
            // No creds configured → challenge forever with a static realm
            return $this->challenge('FS-PBX-Provision');
        }
        $realm = $creds['realm'];

        // 6) Verify HTTP Digest
        $auth = $request->header('Authorization', '');
        if (!str_starts_with($auth, 'Digest ')) {
            return $this->challenge($realm);
        }

        $parts = $this->parseDigest(substr($auth, 7));
        if (!$parts || empty($parts['username']) || empty($parts['nonce']) || empty($parts['uri']) || empty($parts['response'])) {
            return $this->challenge($realm);
        }

        // Username must match domain/default provisioning username
        if (!hash_equals($creds['username'], $parts['username'])) {
            return $this->challenge($realm);
        }

        // Nonce replay/expiry guard (we cache only the nonce, NOT credentials)
        if (!$this->nonceValid($parts['nonce'], $parts['nc'] ?? null)) {
            return $this->challenge($realm, true);
        }

        // Compute expected response
        // HA1 = md5(username:realm:password) — precomputed by ProvisionCredsResolver
        $HA1 = $creds['ha1'];
        // Use the exact URI string the client signed
        $HA2 = md5($request->getMethod() . ':' . $parts['uri']);

        $expected = (isset($parts['qop']) && $parts['qop'] === 'auth')
            ? md5($HA1 . ':' . $parts['nonce'] . ':' . ($parts['nc'] ?? '') . ':' . ($parts['cnonce'] ?? '') . ':auth:' . $HA2)
            : md5($HA1 . ':' . $parts['nonce'] . ':' . $HA2);

        if (!hash_equals($expected, $parts['response'])) {
            return $this->challenge($realm);
        }

        // Optional: annotate request with resolved context for downstream controller
        $request->attributes->set('prov.device', $device);
        $request->attributes->set('prov.domain_uuid', $domain_uuid);
        $request->attributes->set('prov.content_type', $contentType);

        return $next($request);
    }

    /**
     * Support routes:
     *  - /app/provision/{id}.{ext}             → new style
     *  - /app/provision/{path}                 → legacy catch-all (extract from tail)
     */
    private function extractIdAndExt(Request $request): array
    {
        $id  = (string) ($request->route('id')  ?? '');
        $ext = (string) ($request->route('ext') ?? '');

        if ($id !== '' && $ext !== '') {
            return [$id, strtolower($ext)];
        }

        // Legacy: route('path') or full path
        $path = (string) ($request->route('path') ?? $request->path());
        // we only care about the tail after /app/provision/
        if (preg_match('#/app/provision/(.+)$#i', $path, $m)) {
            $tail = $m[1];
        } else {
            $tail = $path;
        }

        // Expect something like: 0004f23a5bc7.cfg or da44-1017-9088-0092.xml
        if (preg_match('#([^/]+)\.(cfg|xml)$#i', $tail, $m)) {
            return [$m[1], strtolower($m[2])];
        }

        // Fallbacks (unlikely): treat whole tail as id, default ext=cfg
        return [$tail, 'cfg'];
    }

    private function challenge(string $realm, bool $stale = false)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);

        $hdr = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s"%s',
            $realm, $nonce, $opaque, $stale ? ', stale=true' : ''
        );

        return response('Unauthorized', 401)->withHeaders([
            'WWW-Authenticate' => $hdr,
        ]);
    }

    /** Cache ONLY the nonce & seen nc’s (no credentials). TTL=300s */
    private function makeNonce(): string
    {
        $nonce = base64_encode(implode(':', [time(), Str::random(16)]));
        Cache::put("prov:nonce:$nonce", ['ts' => time(), 'nc' => []], 300);
        return $nonce;
    }

    private function nonceValid(string $nonce, ?string $nc): bool
    {
        $entry = Cache::get("prov:nonce:$nonce");
        if (!$entry) return false;

        if ($nc) {
            $seen = $entry['nc'] ?? [];
            if (in_array($nc, $seen, true)) return false; // replay
            $seen[] = $nc;
            Cache::put("prov:nonce:$nonce", ['ts' => $entry['ts'], 'nc' => $seen], 300);
        }
        return true;
    }

    private function parseDigest(string $header): array
    {
        $out = [];
        preg_match_all('@(\w+)=("([^"]+)"|([^,]+))@', $header, $m, PREG_SET_ORDER);
        foreach ($m as $pair) {
            $out[$pair[1]] = $pair[3] !== '' ? $pair[3] : $pair[4];
        }
        return $out;
    }
}
