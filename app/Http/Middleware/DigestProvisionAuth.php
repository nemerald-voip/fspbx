<?php 
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Provisioning\{VendorRouter, DeviceLocator, DomainFromRequest, ProvisionCredsResolver};

class DigestProvisionAuth
{
    public function handle(Request $request, Closure $next)
    {
        $path = ltrim($request->route('path') ?? '', '/');
        $ua   = $request->userAgent() ?? '';

        $ctx    = app(VendorRouter::class)->analyze($path, $ua);

        logger($path);
        logger($ua);
        logger(json_encode($ctx ));

        return $next($request);
        $device = app(DeviceLocator::class)->find($ctx);

        // Determine domain_uuid: prefer device, else Host mapping, else null (→ default)
        $domain_uuid = app(DomainFromRequest::class)->resolve($device, $request);

        // Load creds for this domain (fallback to default); if none → challenge forever
        $creds = app(ProvisionCredsResolver::class)->forDomain($domain_uuid);
        if (!$creds) return $this->challenge('FS-PBX-Provision');

        $realm = $creds['realm'];

        // If no Authorization header, challenge
        $auth = $request->header('Authorization', '');
        if (!str_starts_with($auth, 'Digest ')) {
            return $this->challenge($realm);
        }

        // Parse Digest header
        $parts = $this->parseDigest(substr($auth, 7));
        if (!$parts || empty($parts['username']) || empty($parts['nonce']) || empty($parts['uri']) || empty($parts['response'])) {
            return $this->challenge($realm);
        }

        // Username must match the domain/default username
        if (!hash_equals($creds['username'], $parts['username'])) {
            return $this->challenge($realm);
        }

        // Nonce guard
        if (!$this->nonceValid($parts['nonce'], $parts['nc'] ?? null)) {
            return $this->challenge($realm, true);
        }

        // Compute expected response
        $HA1 = $creds['ha1']; // precomputed
        $HA2 = md5($request->method() . ':' . $parts['uri']);

        $expected = isset($parts['qop']) && $parts['qop'] === 'auth'
            ? md5($HA1 . ':' . $parts['nonce'] . ':' . ($parts['nc'] ?? '') . ':' . ($parts['cnonce'] ?? '') . ':auth:' . $HA2)
            : md5($HA1 . ':' . $parts['nonce'] . ':' . $HA2);

        if (!hash_equals($expected, $parts['response'])) {
            return $this->challenge($realm);
        }

        return $next($request);
    }

    private function challenge(string $realm, bool $stale = false)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);

        $hdr = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s"%s',
            $realm, $nonce, $opaque, $stale ? ', stale=true' : ''
        );
        return response('Unauthorized', 401)->withHeaders(['WWW-Authenticate' => $hdr]);
    }

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
            if (in_array($nc, $seen, true)) return false;
            $seen[] = $nc;
            Cache::put("prov:nonce:$nonce", ['ts' => $entry['ts'], 'nc' => $seen], 300);
        }
        return true;
    }

    private function parseDigest(string $header): array
    {
        $out = [];
        preg_match_all('@(\w+)=("([^"]+)"|([^,]+))@', $header, $m, PREG_SET_ORDER);
        foreach ($m as $pair) $out[$pair[1]] = $pair[3] !== '' ? $pair[3] : $pair[4];
        return $out;
    }
}
