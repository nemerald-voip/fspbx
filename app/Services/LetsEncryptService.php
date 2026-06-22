<?php

namespace App\Services;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use skoerfgen\ACMECert\ACMECert;
use Throwable;

/**
 * Issues and installs Let's Encrypt certificates for FreeSWITCH using the
 * HTTP-01 challenge, then hot-reloads them via the FreeSWITCH 1.11.1
 * `reloadcert` API (no restart required).
 *
 * Install layout follows the official FS PBX TLS doc:
 * one combined `all.pem` (fullchain + private key) symlinked to
 * agent.pem / tls.pem / wss.pem / dtls-srtp.pem.
 */
class LetsEncryptService
{
    public const SETTING_CATEGORY = 'tls';

    /** FreeSWITCH TLS directory (owned by www-data on FS PBX installs). */
    protected string $tlsDir = '/etc/freeswitch/tls';

    /** Cert names that FreeSWITCH loads, all symlinked to all.pem. */
    protected array $certNames = ['agent.pem', 'tls.pem', 'wss.pem', 'dtls-srtp.pem'];

    public function __construct(protected FreeswitchEslService $esl) {}

    /**
     * Live status read from the on-disk certificate (disk is source of truth).
     */
    public function status(): array
    {
        $config = $this->config();

        // Prefer the FS PBX-managed all.pem; fall back to the cert FreeSWITCH
        // actually loads (tls.pem) so a pre-existing self-signed cert is reported.
        $allPem = $this->tlsDir.'/all.pem';
        $certPath = is_readable($allPem) ? $allPem : $this->tlsDir.'/tls.pem';

        $cert = [
            'installed' => false,
            'domains' => [],
            'issuer' => null,
            'is_self_signed' => false,
            'is_staging' => false,
            'serial' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'is_lets_encrypt' => false,
            'source' => null,
        ];

        if (is_readable($certPath)) {
            $pem = file_get_contents($certPath);
            $parsed = @openssl_x509_parse($pem);

            if ($parsed) {
                $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
                $issuerText = $this->issuerText($parsed);
                $isStaging = Str::contains($issuerText, '(staging');
                $validTo = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;

                $cert = [
                    'installed' => true,
                    'domains' => $this->extractSans($parsed),
                    'issuer' => $issuer,
                    'is_self_signed' => ($parsed['issuer'] ?? []) == ($parsed['subject'] ?? []),
                    'is_staging' => $isStaging,
                    'serial' => $parsed['serialNumberHex'] ?? ($parsed['serialNumber'] ?? null),
                    'valid_from' => isset($parsed['validFrom_time_t'])
                        ? date('c', (int) $parsed['validFrom_time_t']) : null,
                    'valid_to' => $validTo ? date('c', $validTo) : null,
                    'days_remaining' => $validTo
                        ? (int) floor(($validTo - time()) / 86400) : null,
                    'is_lets_encrypt' => Str::contains($issuerText, ["let's encrypt", 'letsencrypt']),
                    'source' => basename($certPath),
                ];
            }
        }

        return [
            'config' => $config,
            'certificate' => $cert,
            'files' => $this->verifyInstalledFiles(),
        ];
    }

    /**
     * Verify the on-disk install the way `openssl x509 ... ; ls -l` would:
     * confirm all.pem exists and that each FreeSWITCH cert name is a symlink
     * pointing at it (otherwise FreeSWITCH may load a stale certificate).
     *
     * @return array{all_pem: bool, links_ok: bool, links: array<string, string>}
     */
    public function verifyInstalledFiles(): array
    {
        $allPem = $this->tlsDir.'/all.pem';
        $allExists = is_file($allPem);

        $links = [];
        $linksOk = $allExists;

        foreach ($this->certNames as $name) {
            $path = $this->tlsDir.'/'.$name;

            if (! file_exists($path)) {
                $links[$name] = 'missing';
                $linksOk = false;
            } elseif (is_link($path) && realpath($path) === realpath($allPem)) {
                $links[$name] = 'symlink';
            } else {
                // A regular file (or a symlink elsewhere) means it is NOT tracking all.pem.
                $links[$name] = is_link($path) ? 'symlink-other' : 'regular-file';
                $linksOk = false;
            }
        }

        return [
            'all_pem' => $allExists,
            'links_ok' => $linksOk,
            'links' => $links,
        ];
    }

    /**
     * Issue (or renew) a certificate for $domain, install it and reload FreeSWITCH.
     *
     * @return array{domains: array, valid_to: ?string, staging: bool}
     */
    public function issue(string $domain, ?string $email = null, ?bool $staging = null): array
    {
        $config = $this->config();

        // The domain field may list several hostnames (SANs) for failover /
        // multi-node setups, e.g. "pbx.example.com pbx01.example.com".
        $domains = $this->parseDomains($domain);
        if (empty($domains)) {
            throw new RuntimeException('At least one domain is required.');
        }

        $email = $email ?: ($config['account_email'] ?? null);
        $staging = $staging ?? (($config['staging'] ?? 'true') === 'true');
        $webroot = $config['webroot'] ?: $this->defaultWebroot();

        foreach ($domains as $name) {
            $this->assertValidDomain($name);
        }
        $primary = $domains[0];

        if (! $email) {
            throw new RuntimeException('An ACME account email address is required.');
        }

        // $webroot is the document root; ACMECert's HTTP-01 $opts['key'] is the
        // full request path (e.g. /.well-known/acme-challenge/<token>), so the
        // token is written to <webroot>/.well-known/acme-challenge/<token> and
        // served by nginx via `root <webroot>;`.
        $webrootBase = rtrim($webroot, '/');
        $this->ensureDir($webrootBase.'/.well-known/acme-challenge', 0755);
        $this->ensureDir($this->storageDir(), 0700);

        $ac = new ACMECert($staging ? false : true);
        $ac->setLogger(false);
        $ac->loadAccountKey($this->accountKey());

        // Idempotent: returns the existing account when the key is already
        // registered. ACMECert prepends "mailto:" itself, so pass the bare email.
        $ac->register(true, $email ? [$email] : []);

        $domainKey = $this->domainKey();
        $challengePeers = $this->peerHosts($domains);

        $handler = function ($opts) use ($webrootBase, $challengePeers) {
            return $this->publishHttpChallenge(
                $opts['domain'],
                $opts['key'],
                $opts['value'],
                $webrootBase,
                $challengePeers
            );
        };

        $domainConfig = [];
        foreach ($domains as $name) {
            $domainConfig[$name] = ['challenge' => 'http-01'];
        }

        $fullchain = $ac->getCertificateChain($domainKey, $domainConfig, $handler);

        $this->install($fullchain, $domainKey);
        $reload = $this->reloadFreeswitch();

        // Push the root CA to Polycom phones so they trust the new server cert.
        // Polycom validates the full chain up to a self-signed root, so the
        // anchor must be the root (resolved by following the chain's AIA),
        // not an intermediate, which only yields error=2 on the phone.
        $root = $this->fetchRootCertificate($fullchain);
        if ($root) {
            $this->setPolycomCaCert($root);
        } else {
            logger('LetsEncryptService: could not resolve the root CA for Polycom trust; left polycom_custom_ca_cert2 unchanged.');
        }

        $parsed = @openssl_x509_parse($fullchain);
        $validTo = ($parsed && isset($parsed['validTo_time_t']))
            ? date('c', (int) $parsed['validTo_time_t']) : null;

        $this->saveSetting('last_issued', now()->toIso8601String());
        $this->saveSetting('last_error', '');
        $this->saveSetting('domain', implode(' ', $domains));
        if ($email) {
            $this->saveSetting('account_email', $email);
        }
        Cache::forget('scheduled_jobs_settings');

        // Replicate the new bundle to peer nodes (failover / multi-node). A
        // failed push fails the whole operation so it retries and nodes do not
        // diverge (one node holding a newer cert than the other).
        $pushResults = $this->pushToPeers($root, $challengePeers);
        $this->assertPeersInSync($pushResults);

        return [
            'domains' => $parsed ? $this->extractSans($parsed) : $domains,
            'valid_to' => $validTo,
            'staging' => $staging,
            'reload' => $reload,
            'primary' => $primary,
            'peers' => $pushResults,
        ];
    }

    /**
     * Parse the domain field into a normalized, de-duplicated list of FQDNs.
     * Accepts whitespace-, comma-, or newline-separated values.
     *
     * @return array<int, string>
     */
    public function parseDomains(string $value): array
    {
        $parts = preg_split('/[\s,]+/', strtolower(trim($value))) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn ($p) => trim($p, " \t\n\r\0\x0B."),
            $parts
        ))));
    }

    /**
     * Revoke the currently-installed Let's Encrypt certificate at the CA, then
     * replace it with a fresh self-signed certificate so FreeSWITCH keeps
     * serving TLS, and hot-reload.
     *
     * @return array{revoked: bool, staging: bool, reload: string}
     */
    public function revoke(): array
    {
        $allPem = $this->tlsDir.'/all.pem';
        $certPath = is_readable($allPem) ? $allPem : $this->tlsDir.'/tls.pem';

        if (! is_readable($certPath)) {
            throw new RuntimeException('No certificate is installed to revoke.');
        }

        $pem = file_get_contents($certPath);
        $parsed = @openssl_x509_parse($pem);

        if (! $parsed) {
            throw new RuntimeException('Unable to read the installed certificate.');
        }

        $issuer = $this->issuerText($parsed);

        if (! Str::contains($issuer, ["let's encrypt", 'letsencrypt'])) {
            throw new RuntimeException('The installed certificate was not issued by Let\'s Encrypt; there is nothing to revoke.');
        }

        // Revoke against the same CA (staging vs production) that issued it.
        // The "(STAGING)" marker lives in the issuer CN, not the O field.
        $isStaging = Str::contains($issuer, '(staging');

        $ac = new ACMECert($isStaging ? false : true);
        $ac->setLogger(false);
        $ac->loadAccountKey($this->accountKey());
        $ac->revoke($pem); // reads the leaf certificate from the combined PEM

        // Replace with a self-signed cert so TLS profiles keep working. Use the
        // primary (first) configured hostname as the CN.
        $domain = $this->parseDomains((string) ($this->config()['domain'] ?? ''))[0]
            ?? ($this->defaultDomain() ?: 'localhost');
        [$cert, $key] = $this->selfSignedPem($domain);
        $this->install($cert, $key);
        $reload = $this->reloadFreeswitch();

        // Keep Polycom phones in sync: the self-signed cert is its own trust
        // anchor, so push it as the custom CA (replacing the now-revoked LE
        // root) so phones still trust the served cert after a re-provision.
        $this->setPolycomCaCert($cert);

        // Replicate the self-signed replacement to peer nodes; fail if a peer
        // is unreachable so the nodes do not diverge (peer still serving the
        // revoked cert).
        $this->assertPeersInSync($this->pushToPeers($cert));

        $this->saveSetting('last_revoked', now()->toIso8601String());
        $this->saveSetting('last_error', '');

        return [
            'revoked' => true,
            'staging' => $isStaging,
            'reload' => $reload,
        ];
    }

    /**
     * Generate a fresh self-signed certificate + key for $domain (fallback used
     * after revocation so FreeSWITCH still has a usable, non-revoked cert).
     *
     * @return array{0: string, 1: string} [certPem, keyPem]
     */
    protected function selfSignedPem(string $domain): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $pkey = openssl_pkey_new($config);
        if ($pkey === false) {
            throw new RuntimeException('Unable to generate a self-signed private key.');
        }

        // Set the DN explicitly so OpenSSL does not fall back to the openssl.cnf
        // defaults (e.g. O="Internet Widgits Pty Ltd").
        $dn = [
            'organizationName' => 'FS PBX',
            'commonName' => $domain,
        ];

        $csr = openssl_csr_new($dn, $pkey, $config);
        if ($csr === false) {
            throw new RuntimeException('Unable to generate a self-signed CSR.');
        }

        $x509 = openssl_csr_sign($csr, null, $pkey, 3650, $config); // self-signed, ~10 years
        if ($x509 === false) {
            throw new RuntimeException('Unable to sign the self-signed certificate.');
        }

        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($pkey, $keyPem);

        return [$certPem, $keyPem];
    }

    /**
     * Build the combined all.pem (fullchain then key) and install it.
     */
    protected function install(string $fullchainPem, string $domainKeyPem): void
    {
        // fullchain first, then the private key (per FS PBX TLS doc).
        $this->writeBundle(rtrim($fullchainPem)."\n".rtrim($domainKeyPem)."\n");
    }

    /**
     * Validate and install a complete all.pem bundle pushed from a peer node.
     * Returns true if the bundle was new and written, false if it was identical
     * to the installed cert (so the caller can skip the FreeSWITCH reload and
     * avoid churn from idempotent daily re-pushes).
     */
    public function installBundle(string $allPem): bool
    {
        if (! preg_match('/-----BEGIN CERTIFICATE-----/', $allPem)
            || ! preg_match('/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/', $allPem)) {
            throw new RuntimeException('The pushed bundle must contain both a certificate and a private key.');
        }

        if (! @openssl_x509_parse($allPem)) {
            throw new RuntimeException('The pushed certificate could not be parsed.');
        }

        $current = @file_get_contents($this->tlsDir.'/all.pem');
        if ($current !== false && trim($current) === trim($allPem)) {
            return false; // already current
        }

        $this->writeBundle($allPem);

        return true;
    }

    /**
     * Write all.pem atomically and (re)create the FreeSWITCH cert symlinks.
     */
    public function writeBundle(string $allPem): void
    {
        $this->ensureDir($this->tlsDir, 0750);

        $path = $this->tlsDir.'/all.pem';
        $tmp = $path.'.tmp'.bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $allPem) === false) {
            throw new RuntimeException("Unable to write certificate to {$tmp}.");
        }
        @chmod($tmp, 0660);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Unable to move certificate into {$path}.");
        }

        foreach ($this->certNames as $name) {
            $this->atomicSymlink('all.pem', $this->tlsDir.'/'.$name);
        }
    }

    /**
     * Hot-reload SSL/TLS certificates in FreeSWITCH 1.11.1+ (no restart).
     */
    public function reloadFreeswitch(): string
    {
        if (! $this->esl->isConnected()) {
            $this->esl->reconnect();
        }

        $result = trim((string) $this->esl->executeCommand('reloadcert'));
        $this->esl->disconnect();

        return $result !== '' ? $result : '+OK cert reload event sent';
    }

    /**
     * Fetch the just-written challenge token over HTTP (port 80) the same way
     * Let's Encrypt will, to verify the webroot is reachable before triggering
     * validation. Throws a descriptive error on any reachability/content issue.
     */
    protected function verifyChallengeReachable(string $domain, string $path, string $expected): void
    {
        $url = 'http://'.$domain.$path;
        $body = null;
        $error = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true, // LE follows redirects too
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false, // a redirect target may still carry the old/self-signed cert
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $result = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($result === false) {
                $error = curl_error($ch);
            } else {
                $body = $result;
            }
            curl_close($ch);
        } else {
            $result = @file_get_contents($url);
            $body = $result === false ? null : $result;
            $error = $result === false ? 'HTTP request failed' : null;
        }

        if ($body === null) {
            throw new RuntimeException(
                "Pre-flight check failed: could not reach {$url}"
                .($error ? " ({$error})" : '').'. '
                .'Ensure port 80 is open to the internet and nginx serves the ACME webroot '
                .'(/.well-known/acme-challenge/) from the configured document root.'
            );
        }

        if (trim($body) !== trim($expected)) {
            throw new RuntimeException(
                "Pre-flight check failed: {$url} returned HTTP {$httpCode} with unexpected content. "
                .'Confirm the port-80 server block serves /.well-known/acme-challenge/ directly from the '
                .'configured webroot and does not redirect that path to HTTPS.'
            );
        }
    }

    /**
     * Publish one HTTP-01 token locally and to every peer before preflight.
     * Returns the cleanup callback expected by ACMECert.
     */
    protected function publishHttpChallenge(
        string $domain,
        string $path,
        string $value,
        string $webrootBase,
        array $peers
    ): callable {
        $file = $webrootBase.$path;
        $token = basename($path);
        $this->ensureDir(dirname($file), 0755);

        if (file_put_contents($file, $value) === false) {
            throw new RuntimeException("Unable to write ACME challenge token to {$file}.");
        }
        @chmod($file, 0644);

        try {
            // A SAN may resolve to any cluster node. Publish every token to all
            // peers before checking reachability or starting CA validation.
            $this->assertChallengePeersInSync(
                $this->pushChallengeToPeers('present', $token, $value, $peers)
            );

            $this->verifyChallengeReachable($domain, $path, $value);
        } catch (Throwable $exception) {
            @unlink($file);
            $this->cleanupPeerChallenge($token, $peers);

            throw $exception;
        }

        return function () use ($file, $token, $peers) {
            @unlink($file);
            $this->cleanupPeerChallenge($token, $peers);
        };
    }

    protected function atomicSymlink(string $target, string $linkPath): void
    {
        $tmpLink = $linkPath.'.tmp'.bin2hex(random_bytes(4));

        if (! @symlink($target, $tmpLink)) {
            throw new RuntimeException("Unable to create symlink for {$linkPath}.");
        }

        // rename() over an existing file/symlink is atomic on the same filesystem.
        if (! @rename($tmpLink, $linkPath)) {
            @unlink($tmpLink);
            throw new RuntimeException("Unable to replace {$linkPath} with a symlink.");
        }
    }

    /**
     * Load the persisted ACME account key, creating one on first use.
     */
    protected function accountKey(): string
    {
        $path = $this->storageDir().'/account.key';

        if (! is_file($path)) {
            $key = (new ACMECert())->generateECKey('P-384');
            file_put_contents($path, $key);
            @chmod($path, 0600);
        }

        return 'file://'.$path;
    }

    /**
     * Load the persisted domain (certificate) key, creating one on first use.
     */
    protected function domainKey(): string
    {
        $path = $this->storageDir().'/domain.key';

        if (! is_file($path)) {
            $key = (new ACMECert())->generateECKey('P-384');
            file_put_contents($path, $key);
            @chmod($path, 0600);
        }

        return file_get_contents($path);
    }

    protected function storageDir(): string
    {
        return storage_path('app/letsencrypt');
    }

    protected function ensureDir(string $dir, int $mode): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, $mode, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create directory {$dir}.");
        }
    }

    protected function assertValidDomain(string $domain): void
    {
        if (Str::startsWith($domain, '*.')) {
            throw new RuntimeException('Wildcard certificates require DNS-01 and are not supported here.');
        }

        if (! filter_var('http://'.$domain, FILTER_VALIDATE_URL)
            || ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            throw new RuntimeException("Invalid domain name: {$domain}.");
        }
    }

    /**
     * Resolve the self-signed root CA for a certificate chain. Starts at the
     * top-most cert in the served chain and follows the AIA "CA Issuers" URL
     * upward until it reaches a self-signed certificate (the root), which is
     * the trust anchor Polycom phones require.
     */
    protected function fetchRootCertificate(string $chainPem): ?string
    {
        if (! preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $chainPem, $m)) {
            return null;
        }

        $current = trim((string) end($m[0])); // top-most cert in the served chain

        for ($hops = 0; $hops < 5; $hops++) {
            $parsed = @openssl_x509_parse($current);
            if (! $parsed) {
                return null;
            }

            // Self-signed => this is the root.
            if (($parsed['subject'] ?? null) == ($parsed['issuer'] ?? null)) {
                return trim($current);
            }

            $url = $this->aiaCaIssuersUrl($parsed);
            if (! $url) {
                return null;
            }

            $next = $this->fetchCertificatePem($url);
            if (! $next) {
                return null;
            }
            $current = $next;
        }

        return null;
    }

    /**
     * Extract the "CA Issuers" URI from a parsed cert's authorityInfoAccess.
     */
    protected function aiaCaIssuersUrl(array $parsed): ?string
    {
        $aia = $parsed['extensions']['authorityInfoAccess'] ?? '';

        if (preg_match('/CA Issuers - URI:\s*(\S+)/i', (string) $aia, $mm)) {
            return trim($mm[1]);
        }

        return null;
    }

    /**
     * Fetch a certificate (PEM or DER) from a URL and return it as PEM.
     */
    protected function fetchCertificatePem(string $url): ?string
    {
        $data = $this->httpGet($url);
        if ($data === null || $data === '') {
            return null;
        }

        if (str_contains($data, '-----BEGIN CERTIFICATE-----')
            && preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $data, $mm)) {
            return trim($mm[0]);
        }

        // Assume DER; wrap to PEM.
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($data), 64, "\n")."-----END CERTIFICATE-----\n";

        return @openssl_x509_parse($pem) ? trim($pem) : null;
    }

    protected function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result === false ? null : $result;
        }

        $result = @file_get_contents($url);

        return $result === false ? null : $result;
    }

    /**
     * Write the issuing CA certificate into the Polycom custom CA setting so it
     * is provisioned to phones (device.sec.TLS.customCaCert2). Polycom treats it
     * as a trust anchor, so phones trust the FreeSWITCH server cert.
     */
    public function setPolycomCaCert(?string $pem): void
    {
        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'provision',
                'default_setting_subcategory' => 'polycom_custom_ca_cert2',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => (string) $pem,
                'default_setting_enabled' => filled($pem),
            ]
        );
    }

    /**
     * Flatten every component of the issuer DN into one lowercase string. The
     * "(STAGING)" marker lives in the issuer CN while O stays "Let's Encrypt",
     * so staging detection must scan the whole DN, not a single field.
     */
    protected function issuerText(array $parsed): string
    {
        $issuer = $parsed['issuer'] ?? [];
        $parts = [];
        array_walk_recursive($issuer, function ($value) use (&$parts) {
            $parts[] = $value;
        });

        return strtolower(implode(' ', $parts));
    }

    protected function extractSans(array $parsed): array
    {
        $san = $parsed['extensions']['subjectAltName'] ?? '';

        $domains = collect(explode(',', $san))
            ->map(fn ($entry) => trim(str_ireplace('DNS:', '', $entry)))
            ->filter()
            ->values()
            ->all();

        if (! $domains && isset($parsed['subject']['CN'])) {
            $domains = [$parsed['subject']['CN']];
        }

        return $domains;
    }

    /**
     * All `tls`-category settings keyed by subcategory, with sane defaults.
     */
    public function config(): array
    {
        $rows = DefaultSettings::query()
            ->where('default_setting_category', self::SETTING_CATEGORY)
            ->pluck('default_setting_value', 'default_setting_subcategory')
            ->toArray();

        return [
            'domain' => $rows['letsencrypt_domain'] ?? $this->defaultDomain(),
            'account_email' => $rows['letsencrypt_account_email'] ?? null,
            'webroot' => $rows['letsencrypt_webroot'] ?? $this->defaultWebroot(),
            'staging' => $rows['letsencrypt_staging'] ?? 'true',
            'auto_renew' => $rows['letsencrypt_auto_renew'] ?? 'false',
            'push_secret' => $rows['letsencrypt_push_secret'] ?? null,
            'last_issued' => $rows['letsencrypt_last_issued'] ?? null,
            'last_revoked' => $rows['letsencrypt_last_revoked'] ?? null,
            'last_error' => $rows['letsencrypt_last_error'] ?? null,
        ];
    }

    /**
     * Constant-time check of a secret presented by a peer node against the
     * configured shared push secret. Returns false when no secret is set.
     */
    public function verifyPushSecret(?string $presented): bool
    {
        $configured = (string) ($this->config()['push_secret'] ?? '');

        return $configured !== '' && is_string($presented) && hash_equals($configured, $presented);
    }

    /**
     * Store a challenge token received from the active peer node.
     */
    public function storeChallengeToken(string $token, string $value): void
    {
        $path = $this->challengeTokenPath($token);
        $this->ensureDir(dirname($path), 0755);

        if (file_put_contents($path, $value) === false) {
            throw new RuntimeException("Unable to write ACME challenge token to {$path}.");
        }

        @chmod($path, 0644);
    }

    /**
     * Remove a challenge token received from a peer. Missing tokens are safe.
     */
    public function removeChallengeToken(string $token): void
    {
        $path = $this->challengeTokenPath($token);

        if (is_file($path) && ! @unlink($path)) {
            throw new RuntimeException("Unable to remove ACME challenge token from {$path}.");
        }
    }

    protected function challengeTokenPath(string $token): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,255}$/', $token)) {
            throw new RuntimeException('Invalid ACME challenge token.');
        }

        $webroot = rtrim((string) ($this->config()['webroot'] ?: $this->defaultWebroot()), '/');

        return $webroot.'/.well-known/acme-challenge/'.$token;
    }

    /**
     * Peer node base URLs to replicate the cert to, derived from the SAN list:
     * every hostname except the primary (failover record), as https URLs, with
     * this node dropped by IP. So a cluster only needs the domain field filled,
     * and a single server (or one whose extra SANs all resolve to itself) yields
     * no peers and is not treated as a cluster.
     *
     * @return array<int, string>
     */
    public function peerHosts(?array $domains = null): array
    {
        $domains ??= $this->parseDomains((string) ($this->config()['domain'] ?? ''));
        $hosts = array_map(fn ($d) => 'https://'.$d, array_slice($domains, 1));

        if (empty($hosts)) {
            return [];
        }

        $localIps = $this->localIps();

        return array_values(array_filter($hosts, function ($host) use ($localIps) {
            $name = parse_url($host, PHP_URL_HOST);
            if (! $name) {
                return true;
            }

            $ips = @gethostbynamel($name) ?: [];

            return empty(array_intersect($ips, $localIps));
        }));
    }

    /**
     * IP addresses belonging to this node (interface addresses + own hostname),
     * used to recognize and skip the local node within the replicated peer list,
     * and to drive LE's own active-node check (independent of any other feature's
     * settings).
     *
     * @return array<int, string>
     */
    public function localIps(): array
    {
        return Cache::remember('letsencrypt_local_ips', 300, function () {
            $ips = [];

            try {
                $output = Process::timeout(2)->run(['hostname', '-I'])->output();
                $ips = preg_split('/\s+/', trim((string) $output)) ?: [];
            } catch (Throwable) {
                // Fall through to hostname resolution.
            }

            $self = @gethostbynamel(gethostname() ?: '') ?: [];

            return array_values(array_unique(array_filter(array_merge($ips, $self))));
        });
    }

    public function isClustered(): bool
    {
        return ! empty($this->peerHosts());
    }

    /**
     * Replicate the current all.pem (plus the Polycom root) to every peer node's
     * receive endpoint, authorized by the shared secret. Returns a per-host
     * result; the caller passes it to assertPeersInSync() to fail (and retry)
     * the operation if any peer did not accept it, so nodes never diverge.
     *
     * @return array<int, array{host: string, ok: bool, status: int, error: ?string}>
     */
    public function pushToPeers(?string $polycomRoot = null, ?array $hosts = null): array
    {
        $hosts ??= $this->peerHosts();
        $secret = (string) ($this->config()['push_secret'] ?? '');
        $results = [];

        if (empty($hosts)) {
            return $results;
        }

        // Peers exist but no secret: report a failure per host (rather than
        // silently skipping) so assertPeersInSync() fails the operation and the
        // nodes cannot diverge. The admin must configure the push secret.
        if ($secret === '') {
            foreach ($hosts as $host) {
                $results[] = ['host' => $host, 'ok' => false, 'status' => 0, 'error' => 'no peer push secret configured'];
            }

            return $results;
        }

        $allPem = @file_get_contents($this->tlsDir.'/all.pem');
        if ($allPem === false) {
            foreach ($hosts as $host) {
                $results[] = ['host' => $host, 'ok' => false, 'status' => 0, 'error' => 'local all.pem not readable'];
            }

            return $results;
        }

        $payload = ['certificate' => $allPem];
        if (filled($polycomRoot)) {
            $payload['polycom_ca'] = $polycomRoot;
        }

        foreach ($hosts as $host) {
            $url = $host.'/api/letsencrypt/receive-certificate';
            $result = ['host' => $host, 'ok' => false, 'status' => 0, 'error' => null];

            try {
                $response = Http::withHeaders(['X-FsPbx-Cert-Secret' => $secret])
                    ->withOptions(['verify' => false]) // peer may still be serving its old/self-signed cert
                    ->timeout(15)
                    ->post($url, $payload);

                $result['status'] = $response->status();
                $result['ok'] = $response->successful();

                if (! $response->successful()) {
                    $result['error'] = 'HTTP '.$response->status();
                    logger('LetsEncryptService: peer push to '.$host.' failed ('.$result['error'].').');
                }
            } catch (Throwable $e) {
                $result['error'] = $e->getMessage();
                logger('LetsEncryptService: peer push to '.$host.' errored: '.$e->getMessage());
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Publish or remove one HTTP-01 token on every peer node.
     *
     * @return array<int, array{host: string, ok: bool, status: int, error: ?string}>
     */
    public function pushChallengeToPeers(
        string $action,
        string $token,
        ?string $value = null,
        ?array $hosts = null
    ): array {
        $hosts ??= $this->peerHosts();
        $secret = (string) ($this->config()['push_secret'] ?? '');
        $results = [];

        if (empty($hosts)) {
            return $results;
        }

        if ($secret === '') {
            foreach ($hosts as $host) {
                $results[] = ['host' => $host, 'ok' => false, 'status' => 0, 'error' => 'no peer push secret configured'];
            }

            return $results;
        }

        foreach ($hosts as $host) {
            $url = $host.'/api/letsencrypt/challenge';
            $result = ['host' => $host, 'ok' => false, 'status' => 0, 'error' => null];

            try {
                $response = Http::withHeaders(['X-FsPbx-Cert-Secret' => $secret])
                    ->withOptions(['verify' => false])
                    ->timeout(15)
                    ->post($url, array_filter([
                        'action' => $action,
                        'token' => $token,
                        'value' => $value,
                    ], fn ($item) => $item !== null));

                $result['status'] = $response->status();
                $result['ok'] = $response->successful();

                if (! $response->successful()) {
                    $result['error'] = 'HTTP '.$response->status();
                    logger("LetsEncryptService: peer challenge {$action} on {$host} failed ({$result['error']}).");
                }
            } catch (Throwable $exception) {
                $result['error'] = $exception->getMessage();
                logger("LetsEncryptService: peer challenge {$action} on {$host} errored: {$exception->getMessage()}");
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function cleanupPeerChallenge(string $token, array $hosts): void
    {
        foreach ($this->pushChallengeToPeers('cleanup', $token, null, $hosts) as $result) {
            if (empty($result['ok'])) {
                logger('LetsEncryptService: unable to clean up challenge token on '
                    .$result['host'].': '.($result['error'] ?? 'unknown error'));
            }
        }
    }

    protected function assertChallengePeersInSync(array $results): void
    {
        $failed = array_values(array_filter($results, fn ($result) => empty($result['ok'])));

        if (empty($failed)) {
            return;
        }

        $hosts = implode(', ', array_map(
            fn ($result) => $result['host'].' ('.($result['error'] ?? 'unknown error').')',
            $failed
        ));

        throw new RuntimeException(
            'Failed to publish the ACME challenge token to peer node(s): '.$hosts
            .'. Certificate validation was not started.'
        );
    }

    /**
     * Throw if any peer push failed, so the caller treats the operation as
     * failed and retries — preventing one node from carrying a newer cert than
     * its peers. The local cert is already installed at this point; the error
     * makes the divergence visible and schedules a retry.
     */
    public function assertPeersInSync(array $pushResults): void
    {
        $failed = array_values(array_filter($pushResults, fn ($r) => empty($r['ok'])));

        if (! empty($failed)) {
            $hosts = implode(', ', array_map(
                fn ($r) => $r['host'].' ('.($r['error'] ?? 'unknown error').')',
                $failed
            ));

            throw new RuntimeException(
                'Certificate installed locally but failed to replicate to peer node(s): '.$hosts
                .'. The operation is marked failed so it retries and nodes do not diverge.'
            );
        }
    }

    public function defaultDomain(): string
    {
        return (string) (parse_url(config('app.url'), PHP_URL_HOST) ?: '');
    }

    /**
     * Document root for HTTP-01 challenges. Defaults to Laravel's public dir
     * (already www-data-owned), where the token is written to
     * <webroot>/.well-known/acme-challenge/<token> and served by nginx via
     * `root /var/www/fspbx/public;`. The dehydrated web-cert script points its
     * WELLKNOWN at this same .well-known/acme-challenge dir, so both ACME
     * clients share one webroot without the /var/www/dehydrated alias.
     */
    public function defaultWebroot(): string
    {
        return public_path();
    }

    public function saveSetting(string $key, ?string $value): void
    {
        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => self::SETTING_CATEGORY,
                'default_setting_subcategory' => 'letsencrypt_'.$key,
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => (string) $value,
                'default_setting_enabled' => 'true',
            ]
        );
    }

    /**
     * Mirror the auto-renew flag onto the `scheduled_jobs` toggle that the
     * console Kernel reads to decide whether to schedule the renewal job.
     */
    public function saveScheduledJobToggle(bool $enabled): void
    {
        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'scheduled_jobs',
                'default_setting_subcategory' => 'renew_tls_certificates',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => $enabled ? 'true' : 'false',
                'default_setting_enabled' => 'true',
            ]
        );

        Cache::forget('scheduled_jobs_settings');
    }

    /**
     * Record a failure for display on the page and in scheduled-renewal logs.
     */
    public function recordError(Throwable $e): void
    {
        $this->saveSetting('last_error', $e->getMessage());
    }
}
