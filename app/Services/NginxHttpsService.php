<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads and changes the FS PBX HTTPS hardening (stricter TLS settings and the HSTS header).
 *
 * The hardening is one nginx snippet that the HTTPS server block of the FS PBX site includes. The
 * snippet on disk is the source of truth. It is rewritten by a small root helper that FS PBX
 * starts through sudo; the helper checks the result with `nginx -t` and puts the old file back if
 * nginx rejects it.
 */
class NginxHttpsService
{
    public const HELPER = '/usr/local/sbin/fspbx-apply-nginx-https';
    public const SNIPPET = '/etc/nginx/snippets/fspbx-https.conf';
    public const SITE = '/etc/nginx/sites-available/fspbx.conf';
    public const CERTIFICATE = '/etc/nginx/ssl/fullchain.pem';

    /** Exit codes of the helper that have a message of their own. */
    private const NOT_READY = 2;
    private const SELF_SIGNED = 3;
    private const NGINX_REJECTED = 4;
    private const RELOAD_FAILED = 5;

    /** @var callable(array): array{int, string} */
    private $runner;

    /**
     * @param  callable(array): array{int, string}|null  $runner  Runs a command, returns [exit code, output].
     * @param  array<string, string>  $paths  Overrides for helper, snippet, site and certificate (tests).
     */
    public function __construct(?callable $runner = null, private array $paths = [])
    {
        $this->runner = $runner ?? function (array $command): array {
            $process = new Process($command, null, null, null, 60);
            $process->run();

            return [$process->getExitCode() ?? 1, trim($process->getOutput().$process->getErrorOutput())];
        };
    }

    /**
     * Current state, read from disk.
     *
     * @return array{enabled: bool, helper_installed: bool, site_ready: bool|null, certificate: array, requires_confirmation: bool}
     */
    public function status(): array
    {
        $snippet = $this->path('snippet');
        $contents = is_readable($snippet) ? (string) file_get_contents($snippet) : '';
        $certificate = $this->certificate();

        return [
            'enabled' => (bool) preg_match('/^# fspbx-https: on$/m', $contents),
            'helper_installed' => is_file($this->path('helper')),
            'site_ready' => $this->siteReady(),
            'certificate' => $certificate,
            'requires_confirmation' => $certificate['installed'] && $certificate['is_self_signed'],
        ];
    }

    /**
     * Turn the hardening on or off.
     *
     * @throws RuntimeException with a message that can be shown to the user
     */
    public function apply(bool $enable, bool $allowSelfSigned = false): array
    {
        if (! is_file($this->path('helper'))) {
            throw new RuntimeException(__('The HTTPS helper is not installed. Run the FS PBX update first.'));
        }

        $command = ['sudo', '-n', $this->path('helper'), $enable ? 'on' : 'off'];

        if ($enable && $allowSelfSigned) {
            $command[] = '--allow-self-signed';
        }

        try {
            [$code, $output] = ($this->runner)($command);
        } catch (Throwable $exception) {
            throw new RuntimeException(__('The HTTPS helper could not be started.'), 0, $exception);
        }

        if ($code !== 0) {
            throw new RuntimeException($this->messageFor($code, $output));
        }

        return $this->status();
    }

    private function messageFor(int $code, string $output): string
    {
        return match ($code) {
            self::NOT_READY => __('The nginx site does not include the HTTPS setting yet. Run the FS PBX update first.'),
            self::SELF_SIGNED => __('The nginx certificate is self-signed. Install a trusted certificate first, or confirm that you understand the risk.'),
            self::NGINX_REJECTED => __('nginx rejected the new configuration. The previous one was restored.'),
            self::RELOAD_FAILED => __('nginx could not be reloaded. The previous configuration was restored.'),
            default => str_contains($output, 'password is required')
                ? __('The web server user is not allowed to run the HTTPS helper. Run the FS PBX update first.')
                : __('The HTTPS setting could not be changed.'),
        };
    }

    private function siteReady(): ?bool
    {
        $site = $this->path('site');

        if (! is_readable($site)) {
            return null;
        }

        return str_contains((string) file_get_contents($site), 'include snippets/fspbx-https.conf;');
    }

    /**
     * What nginx serves on 443, so the page can warn about a self-signed certificate.
     *
     * @return array{installed: bool, is_self_signed: bool, issuer: string|null, valid_to: string|null, domains: array<int, string>}
     */
    private function certificate(): array
    {
        $empty = ['installed' => false, 'is_self_signed' => false, 'issuer' => null, 'valid_to' => null, 'domains' => []];
        $path = $this->path('certificate');

        if (! is_readable($path)) {
            return $empty;
        }

        $parsed = @openssl_x509_parse((string) file_get_contents($path));

        if (! $parsed) {
            return $empty;
        }

        $domains = [];
        foreach (explode(',', (string) ($parsed['extensions']['subjectAltName'] ?? '')) as $entry) {
            $entry = trim($entry);
            if (str_starts_with($entry, 'DNS:')) {
                $domains[] = substr($entry, 4);
            }
        }

        return [
            'installed' => true,
            'is_self_signed' => ($parsed['issuer'] ?? []) == ($parsed['subject'] ?? []),
            'issuer' => $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null),
            'valid_to' => isset($parsed['validTo_time_t']) ? date('c', (int) $parsed['validTo_time_t']) : null,
            'domains' => $domains,
        ];
    }

    private function path(string $key): string
    {
        return $this->paths[$key] ?? match ($key) {
            'helper' => self::HELPER,
            'snippet' => self::SNIPPET,
            'site' => self::SITE,
            'certificate' => self::CERTIFICATE,
        };
    }
}
