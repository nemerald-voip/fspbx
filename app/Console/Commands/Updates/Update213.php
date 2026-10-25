<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;
use Throwable;

class Update213
{
    private const VERSION = '2.1.3';
    private const NGINX_CONFIGS = [
        '/etc/nginx/sites-available/fspbx.conf',
        '/etc/nginx/sites-enabled/fspbx.conf',
    ];

    // Where each set of redirect locations goes. The install template has every
    // anchor once in each of its two server blocks, so a site file has two of each.
    private const AFTER_LOGOUT_BLOCK = 'after_logout_block';
    private const BEFORE_POLYCOM_LOGS = 'before_polycom_logs';
    private const BEFORE_PHP_HANDLER = 'before_php_handler';
    private const AFTER_ERROR_PAGE = 'after_error_page';

    private const LITERAL_ANCHORS = [
        self::BEFORE_POLYCOM_LOGS => ['before', "        #Implement Polycom log upload\n"],
        self::BEFORE_PHP_HANDLER => ['before', "        location ~ \\.php$ {\n"],
        self::AFTER_ERROR_PAGE => ['after', "        error_page 404 /index.php;\n"],
    ];

    // Legacy module directory => FS PBX page that replaced it. The sets mirror the
    // redirect blocks in install/nginx_site_config.conf, one set per insertion point.
    private const SETS = [
        [
            'marker' => 'More legacy FusionPBX pages that now live in the FS PBX UI',
            'anchor' => self::AFTER_LOGOUT_BLOCK,
            'redirects' => [
                'call_flows' => 'call-flows',
                'music_on_hold' => 'music-on-hold',
                'sip_status' => 'sip-status',
                'registrations' => 'registrations',
                'system' => 'system',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX number translations page that now lives in System Settings',
            'anchor' => self::BEFORE_POLYCOM_LOGS,
            'redirects' => [
                'number_translations' => 'system-settings?tab=number_translations',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX SIP profile and gateway pages that now live in the FS PBX UI',
            'anchor' => self::BEFORE_PHP_HANDLER,
            'redirects' => [
                'sip_profiles' => 'sip-profiles',
                'gateways' => 'gateways',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX bridge and access control pages that now live in the FS PBX UI',
            'anchor' => self::AFTER_ERROR_PAGE,
            'redirects' => [
                'bridges' => 'bridges',
                'access_controls' => 'access-controls',
            ],
        ],
    ];

    public function apply(): bool
    {
        try {
            $path = $this->nginxConfigPath();

            if ($path === null) {
                echo "FS PBX Nginx site not found. Skipping legacy page redirects.\n";

                return true;
            }

            $config = file_get_contents($path);

            if ($config === false) {
                throw new \RuntimeException('Unable to read '.$path.'.');
            }

            $updated = $this->updateNginxConfig($config);

            if ($updated === $config) {
                echo "Nginx already has these legacy page redirects, or the site file has none of the places they are added. No change.\n";

                return true;
            }

            $this->writeFile($path.'.bak-'.self::VERSION, $config);
            $this->writeFile($path, $updated);

            if (! $this->runProcess(['nginx', '-t'])) {
                $this->writeFile($path, $config);
                echo "Nginx validation failed. Restored the existing site configuration.\n";

                // Cosmetic change: do not block the rest of the update.
                return true;
            }

            if (! $this->runProcess(['systemctl', 'reload', 'nginx'])) {
                echo "Nginx could not be reloaded. The redirects will apply after the next restart.\n";
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }

    /**
     * Add every redirect set whose marker is not in the config yet, at its own
     * insertion point. A set whose insertion point is missing (a customised
     * site file) is skipped; the others are still added.
     */
    public function updateNginxConfig(string $config): string
    {
        foreach (self::SETS as $set) {
            if (str_contains($config, '# '.$set['marker'])) {
                continue;
            }

            $config = $this->insertSet($config, $set);
        }

        return $config;
    }

    private function insertSet(string $config, array $set): string
    {
        $block = $this->redirectBlock($set['marker'], $set['redirects']);

        if ($set['anchor'] === self::AFTER_LOGOUT_BLOCK) {
            $updated = preg_replace_callback(
                '/^[ \t]*location\s*=\s*\/logout\.php\s*\{[^}]*\}[ \t]*\R/m',
                fn (array $match) => $match[0]."\n".$block,
                $config
            );

            return $updated ?? $config;
        }

        [$where, $anchor] = self::LITERAL_ANCHORS[$set['anchor']];

        if (! str_contains($config, $anchor)) {
            return $config;
        }

        return str_replace(
            $anchor,
            $where === 'before' ? $block."\n".$anchor : $anchor."\n".$block,
            $config
        );
    }

    private function redirectBlock(string $marker, array $redirects): string
    {
        $block = '        # '.$marker."\n";

        foreach ($redirects as $legacy => $target) {
            $block .= '        location ~ ^/app/'.$legacy."/ {\n";
            $block .= '                return 301 https://$host/'.$target.";\n";
            $block .= "        }\n";
        }

        return $block;
    }

    private function nginxConfigPath(): ?string
    {
        foreach (self::NGINX_CONFIGS as $candidate) {
            if (is_file($candidate)) {
                // Follow the sites-enabled symlink so the real file is edited.
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write '.$path.'.');
        }
    }

    private function runProcess(array $command): bool
    {
        try {
            $process = new Process($command, null, null, null, 60);
            $process->run(function (string $type, string $buffer) {
                echo $buffer;
            });

            return $process->isSuccessful();
        } catch (Throwable $exception) {
            echo 'Warning: Unable to run '.implode(' ', $command).': '.$exception->getMessage()."\n";

            return false;
        }
    }
}
