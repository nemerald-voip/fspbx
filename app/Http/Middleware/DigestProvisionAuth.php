<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Provisioning\ProvisioningAuthPolicy;
use App\Services\Provisioning\ProvisioningSettingsResolver;
use App\Services\Provisioning\VendorRouter;

class DigestProvisionAuth
{
    public function __construct(
        private ProvisioningSettingsResolver $settingsResolver,
        private ProvisioningAuthPolicy $authPolicy
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        // turn debug off or on 
        $debug = false;

        [$id, $ext] = $this->extractIdAndExt($request);

        // Send 404 if requested file matches one of the generic names
        $lower = strtolower($id);
        if (in_array($lower, ['default', 'index', 'master', '000000000000', 'sip.ld', 'sip_59x.ld', 'cfg.xml'], true)) {
            $this->dbg($debug, 'early-404.generic', ['path' => $request->getRequestUri()]);
            return response('', 404);
        }

        // Send 404 if requested file extension matches one of the generic names
        $lower = strtolower($ext);
        if (in_array($lower, ['boot'], true)) {
            $this->dbg($debug, 'early-404.generic', ['path' => $request->getRequestUri()]);
            return response('', 404);
        }

        $token = VendorRouter::tokenFromId($id);
        $device = $token ? VendorRouter::findDeviceByToken($token) : null;

        if (!$device) {
            // Some phones append a fixed filename to a directory path (e.g.
            // Grandstream requests <server>/phonebook.xml), so the device token
            // is an earlier path segment rather than the basename. Fall back to
            // scanning the path segments for a resolvable device token.
            $device = $this->deviceFromPathSegments($request);
        }

        if (!$device) {
            $this->dbg($debug, '404.device-not-found', ['token' => $token, 'id' => $id]);
            return response('', 404);
        }

        $domainUuid = (string) $device->domain_uuid;
        $settings = $this->settingsResolver->resolve($domainUuid);
        $cidrs = $this->authPolicy->cidrs($settings);

        if (!$this->authPolicy->clientIpAllowed($cidrs, (string) $request->ip())) {
            $this->dbg($debug, '404.cidr-denied', [
                'domain_uuid' => $domainUuid,
                'client_ip' => $request->ip(),
            ]);

            return response('', 404);
        }

        $hash  = substr(hash_hmac('sha256', (string) $domainUuid, config('app.key')), 0, 16);
        $realm = "Prov-$hash";
        $username = $this->authPolicy->username($settings);
        $passwords = $this->authPolicy->passwords($settings);

        // Match FusionPBX provisioning behavior: CIDR and HTTP authentication
        // are cumulative when both are configured. HTTP authentication is
        // skipped unless both a username and at least one password are set.
        if (!$this->authPolicy->requiresHttpAuthentication($settings)) {
            $this->attach($request, $device, $domainUuid, $realm, $cidrs === [] ? 'none' : 'cidr');
            $this->dbg($debug, 'auth.not-configured', ['domain_uuid' => $domainUuid]);

            return $next($request);
        }

        if ($this->userAgentForcesBasic($request)) {
            $authTypeValue = 'basic';
            $authType = 'basic';
        } else {

            $authTypeValue = $settings['http_auth_type'] ?? null;
            $authType = strtolower(is_scalar($authTypeValue) ? (string) $authTypeValue : '');

            if (!in_array($authType, ['basic', 'digest', 'both'], true)) {
                $authType = 'digest';
            }
        }


        $auth   = $request->header('Authorization', '');
        $scheme = strtolower(strtok($auth, ' ')) ?: '';

        $this->dbg($debug, 'auth.check', [
            'policy' => $authType,
            'scheme' => $scheme,
            'path'   => $request->getRequestUri(),
        ]);

        // BASIC
        if ($scheme === 'basic' && ($authType === 'basic' || $authType === 'both')) {
            if (preg_match('/^Basic\s+(.+)$/i', $auth, $m)) {
                $decoded = base64_decode($m[1], true) ?: '';
                [$bu] = array_pad(explode(':', $decoded, 2), 2, '');
                $this->dbg($debug, 'auth.basic.present', ['user' => $this->maskUser($bu)]);

                if (hash_equals($username, $bu) && $this->passwordMatches($passwords, substr($decoded, strlen($bu) + 1))) {
                    $this->attach($request, $device, $domainUuid, $realm, 'basic');
                    $this->dbg($debug, 'auth.basic.ok');
                    return $next($request);
                }
            }
            $this->dbg($debug, 'auth.basic.fail');
            return $this->challengeAccordingToPolicy($authType, $realm, $debug);
        }

        // DIGEST
        if ($scheme === 'digest' && ($authType === 'digest' || $authType === 'both')) {
            $parts = $this->parseDigest(substr($auth, 7));
            $this->dbg($debug, 'auth.digest.parts', array_intersect_key($parts ?? [], array_flip(['username', 'nonce', 'uri', 'qop', 'nc'])));

            if (!$parts || empty($parts['username']) || empty($parts['nonce']) || empty($parts['uri']) || empty($parts['response'])) {
                return $this->challengeDigest($realm, false, $debug);
            }
            if (!hash_equals($username, $parts['username'])) {
                return $this->challengeDigest($realm, false, $debug);
            }
            if (!$this->nonceValid($parts['nonce'], $parts['nc'] ?? null)) {
                return $this->challengeDigest($realm, true, $debug);
            }

            $HA2 = md5($request->getMethod() . ':' . $parts['uri']);
            $validPassword = collect($passwords)->contains(function (string $password) use ($username, $realm, $parts, $HA2) {
                $HA1 = md5($username . ':' . $realm . ':' . $password);
                $expected = (isset($parts['qop']) && $parts['qop'] === 'auth')
                    ? md5($HA1 . ':' . $parts['nonce'] . ':' . ($parts['nc'] ?? '') . ':' . ($parts['cnonce'] ?? '') . ':auth:' . $HA2)
                    : md5($HA1 . ':' . $parts['nonce'] . ':' . $HA2);

                return hash_equals($expected, $parts['response']);
            });

            if (!$validPassword) {
                $this->dbg($debug, 'auth.digest.bad-response');
                return $this->challengeDigest($realm, false, $debug);
            }

            $this->attach($request, $device, $domainUuid, $realm, 'digest');
            $this->dbg($debug, 'auth.digest.ok');
            return $next($request);
        }

        // No/other auth
        $this->dbg($debug, 'auth.missing-or-unsupported', ['scheme' => $scheme]);
        return $this->challengeAccordingToPolicy($authType, $realm, $debug);
    }

    /* ------------------------- helpers ------------------------- */

    private function attach(Request $req, $device, string $domainUuid, string $realm, string $mode): void
    {
        $req->attributes->set('prov.device', $device);
        $req->attributes->set('prov.domain_uuid', $domainUuid);
        $req->attributes->set('prov.realm', $realm);
        $req->attributes->set('prov.auth_mode', $mode);
    }

    private function dbg(bool $enabled, string $msg, array $ctx = []): void
    {
        if ($enabled) {
            // Never log secrets; caller already redacts values
            logger()->channel(config('logging.default', 'stack'))->info("prov-debug: {$msg}", $ctx);
        }
    }

    private function maskUser(?string $u): string
    {
        if (!$u) return '';
        return strlen($u) <= 2 ? '*'
            : substr($u, 0, 1) . str_repeat('*', max(1, strlen($u) - 2)) . substr($u, -1);
    }

    private function passwordMatches(array $passwords, string $candidate): bool
    {
        foreach ($passwords as $password) {
            if (hash_equals($password, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a device by scanning each path segment for a MAC/serial token.
     * Used when the basename is a fixed filename (e.g. phonebook.xml) rather
     * than the device identifier.
     */
    private function deviceFromPathSegments(Request $request)
    {
        $path = (string) ($request->route('path') ?? $request->path());
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'prov/')) {
            $path = substr($path, 5);
        }

        $segments = array_values(array_filter(explode('/', $path)));

        foreach (array_reverse($segments) as $segment) {
            $token = VendorRouter::tokenFromId($segment);
            if (!$token) {
                continue;
            }

            $device = VendorRouter::findDeviceByToken($token);
            if ($device) {
                return $device;
            }
        }

        return null;
    }

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
        if (preg_match('#^([^/]+)\.(cfg|xml|boot)$#i', $tail, $m)) {
            return [$m[1], strtolower($m[2])];
        }
        return [$tail, 'cfg'];
    }


    //Override known user-agents that do not support digest authentication
    private function userAgentForcesBasic(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        
        //switch this method to cache method when default settings page is eventually migrated 
        $forceBasicMatchers = [
            'algo',
            'panasonic',
            'aastra',
            'snom',
            'DAG', //Dinstar
        ];

        foreach ($forceBasicMatchers as $needle) {
            if (str_contains($ua, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    // Challenge according to policy
    private function challengeAccordingToPolicy(string $authType, string $realm, bool $debug)
    {
        return match ($authType) {
            'digest' => $this->challengeDigest($realm, false, $debug),
            'both'   => $this->challengeBoth($realm, false, $debug),
            default  => $this->challengeBasic($realm, $debug),
        };
    }

    // ---- Basic-only challenge
    private function challengeBasic(string $realm, bool $debug)
    {
        $hdr = sprintf('Basic realm="%s", charset="UTF-8"', $realm);
        $this->dbg($debug, 'challenge.basic', ['hdr' => $hdr]);
        return response('', 401)->withHeaders(['WWW-Authenticate' => $hdr]);
    }

    // ---- Digest-only challenge
    private function challengeDigest(string $realm, bool $stale, bool $debug)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);
        $hdr = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s", algorithm=MD5%s',
            $realm,
            $nonce,
            $opaque,
            $stale ? ', stale=true' : ''
        );
        $this->dbg($debug, 'challenge.digest', ['hdr' => $hdr]);
        return response('', 401)->withHeaders(['WWW-Authenticate' => $hdr]);
    }

    // ---- Both challenges (Digest preferred by clients that support it)
    private function challengeBoth(string $realm, bool $stale, bool $debug)
    {
        $nonce  = $this->makeNonce();
        $opaque = md5(config('app.key') . $realm);
        $digest = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s", algorithm=MD5%s',
            $realm,
            $nonce,
            $opaque,
            $stale ? ', stale=true' : ''
        );
        $basic  = sprintf('Basic realm="%s", charset="UTF-8"', $realm);
        $this->dbg($debug, 'challenge.both', ['digest' => $digest, 'basic' => $basic]);

        $resp = response('', 401);
        $resp->headers->set('WWW-Authenticate', $digest, false);
        $resp->headers->set('WWW-Authenticate', $basic,  false);
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
