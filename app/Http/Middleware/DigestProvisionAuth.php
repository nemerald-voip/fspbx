<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Provisioning\VendorRouter;

class DigestProvisionAuth
{
    public function handle(Request $request, Closure $next)
    {
        // 1) Parse {id}.{ext} and build token (skip if no digits → 404)
        [$id, $ext] = $this->extractIdAndExt($request);

        // Early 404s for files we never serve (cheap, no auth)
        $lower = strtolower($id);
        if (in_array($lower, ['default', 'index', 'master', '000000000000', 'sip.ld', 'sip_59x.ld'], true)) {
            return response('', 404);
        }

        // Token must contain digits (skip words like "default")
        $token = VendorRouter::tokenFromId($id);
        if (!$token) return response('', 404);

        // Lookup by MAC (normalized) OR serial (as stored)
        $device = VendorRouter::findDeviceByToken($token);
        if (!$device) return response('', 404);

        // Per-domain creds (no caching of creds)
        $domainUuid = $device->domain_uuid;
        $username   = get_domain_setting('http_auth_username', $domainUuid);
        $password   = get_domain_setting('http_auth_password', $domainUuid);
        if (!$username || !$password) {
            // Device is known but creds misconfigured → stop
            return response('', 401);
        }

        // Realm derived from domain UUID (stable)
        $base  = (string) $domainUuid;
        $hash  = substr(hash_hmac('sha256', $base, config('app.key')), 0, 16);
        $realm = "Prov-$hash";

        // Auth policy from settings (default to 'basic')
        $authTypeRaw = get_domain_setting('http_auth_type', $domainUuid);
        $authType = in_array(strtolower((string)$authTypeRaw), ['basic', 'digest', 'both'], true)
            ? strtolower((string)$authTypeRaw)
            : 'basic';

        // HTTP Authorization header
        $auth   = $request->header('Authorization', '');
        $scheme = strtolower(strtok($auth, ' ')) ?: '';

        logger()->info("prov-auth policy={$authType} scheme={$scheme} method={$request->getMethod()} path={$request->getRequestUri()} realm={$realm}");

        // -------------------- BASIC path --------------------
        if ($scheme === 'basic' && ($authType === 'basic' || $authType === 'both')) {
            if (preg_match('/^Basic\s+(.+)$/i', $auth, $m)) {
                $decoded = base64_decode($m[1], true) ?: '';
                [$bu, $bp] = array_pad(explode(':', $decoded, 2), 2, '');
                logger()->info("prov-auth basic-user={$bu}");

                if (hash_equals($username, $bu) && hash_equals($password, $bp)) {
                    // Authenticated → attach context and continue
                    $request->attributes->set('prov.device', $device);
                    $request->attributes->set('prov.domain_uuid', $domainUuid);
                    $request->attributes->set('prov.realm', $realm);
                    $request->attributes->set('prov.auth_mode', 'basic');
                    return $next($request);
                }
            }
            // Wrong basic creds
            return $this->challengeAccordingToPolicy($authType, $realm);
        }

        // -------------------- DIGEST path -------------------
        if ($scheme === 'digest' && ($authType === 'digest' || $authType === 'both')) {
            $parts = $this->parseDigest(substr($auth, 7));
            logger()->info('prov-auth digest-parts', $parts ?: []);

            if (
                !$parts || empty($parts['username']) || empty($parts['nonce']) ||
                empty($parts['uri'])      || empty($parts['response'])
            ) {
                return $this->challengeDigest($realm);
            }
            if (!hash_equals($username, $parts['username'])) {
                return $this->challengeDigest($realm);
            }
            if (!$this->nonceValid($parts['nonce'], $parts['nc'] ?? null)) {
                return $this->challengeDigest($realm, true);
            }

            $HA1 = md5($username . ':' . $realm . ':' . $password);
            $HA2 = md5($request->getMethod() . ':' . $parts['uri']);
            $expected = (isset($parts['qop']) && $parts['qop'] === 'auth')
                ? md5($HA1 . ':' . $parts['nonce'] . ':' . ($parts['nc'] ?? '') . ':' . ($parts['cnonce'] ?? '') . ':auth:' . $HA2)
                : md5($HA1 . ':' . $parts['nonce'] . ':' . $HA2);

            if (!hash_equals($expected, $parts['response'])) {
                return $this->challengeDigest($realm);
            }

            // Authenticated → attach context and continue
            $request->attributes->set('prov.device', $device);
            $request->attributes->set('prov.domain_uuid', $domainUuid);
            $request->attributes->set('prov.realm', $realm);
            $request->attributes->set('prov.auth_mode', 'digest');
            return $next($request);
        }

        // -------------------- No/other auth -----------------
        return $this->challengeAccordingToPolicy($authType, $realm);
    }

    /* ------------------------- helpers ------------------------- */

    private function extractIdAndExt(Request $request): array
    {
        $id  = (string) ($request->route('id')  ?? '');
        $ext = (string) ($request->route('ext') ?? '');
        if ($id !== '' && $ext !== '') return [$id, strtolower($ext)];
    
        // Catch-all path like "prov/83/da44-1017-9088-0092.xml"
        $path = (string) ($request->route('path') ?? $request->path());
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'prov/')) $path = substr($path, 5);
    
        // Use the last segment only
        $tail = basename($path);
        if (preg_match('#^([^/]+)\.(cfg|xml)$#i', $tail, $m)) {
            return [$m[1], strtolower($m[2])];
        }
        return [$tail, 'cfg'];
    }
    

    // Challenge according to policy
    private function challengeAccordingToPolicy(string $authType, string $realm)
    {
        switch ($authType) {
            case 'digest': return $this->challengeDigest($realm);
            case 'both':   return $this->challengeBoth($realm);
            case 'basic':
            default:       return $this->challengeBasic($realm);
        }
    }

    // ---- Basic-only challenge
    private function challengeBasic(string $realm)
    {
        $hdr = sprintf('Basic realm="%s", charset="UTF-8"', $realm);
        logger()->info("prov-auth challenge basic hdr={$hdr}");
        $resp = response('', 401);
        $resp->headers->set('WWW-Authenticate', $hdr);
        return $resp;
    }

    // ---- Digest-only challenge
    private function challengeDigest(string $realm, bool $stale = false)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);

        $hdr = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s", algorithm=MD5%s',
            $realm, $nonce, $opaque, $stale ? ', stale=true' : ''
        );
        logger()->info("prov-auth challenge digest hdr={$hdr}");

        return response('', 401)->withHeaders(['WWW-Authenticate' => $hdr]);
    }

    // ---- Both challenges (Digest preferred by clients that support it)
    private function challengeBoth(string $realm, bool $stale = false)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);

        $digest = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s", algorithm=MD5%s',
            $realm, $nonce, $opaque, $stale ? ', stale=true' : ''
        );
        $basic  = sprintf('Basic realm="%s", charset="UTF-8"', $realm);

        logger()->info("prov-auth challenge both digest={$digest} basic={$basic}");

        $resp = response('', 401);
        $resp->headers->set('WWW-Authenticate', $digest, false); // append
        $resp->headers->set('WWW-Authenticate', $basic,  false); // append
        return $resp;
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
        foreach ($m as $pair) {
            $out[$pair[1]] = $pair[3] !== '' ? $pair[3] : trim($pair[4]);
        }
        return $out;
    }
}
