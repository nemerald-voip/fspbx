<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;
use Throwable;

class Update191
{
    private const VERSION = '1.9.1';
    private const DEHYDRATED_CONFIG = '/etc/dehydrated/config';
    private const LEGACY_WELLKNOWN = '/var/www/dehydrated';
    private const WELLKNOWN = '/var/www/fspbx/public/.well-known/acme-challenge';
    private const NGINX_CONFIG = '/etc/nginx/sites-available/fspbx.conf';

    public function apply(): bool
    {
        try {
            $this->ensureWellKnownDirectory();

            $dehydratedConfig = $this->readDehydratedConfig();
            $updatedDehydratedConfig = $dehydratedConfig;

            if ($dehydratedConfig !== null && $this->usesManagedWellKnownDirectory($dehydratedConfig)) {
                $this->moveLegacyChallengeFiles();
                $updatedDehydratedConfig = $this->updateDehydratedConfig($dehydratedConfig);
            } elseif ($dehydratedConfig !== null) {
                echo "Dehydrated uses a custom WELLKNOWN directory. Leaving it unchanged.\n";
            }

            $nginxConfig = $this->readNginxConfig();
            $updatedNginxConfig = $this->updateNginxConfig($nginxConfig);

            if (! $this->hasDefaultAcmeServer($updatedNginxConfig)) {
                throw new \RuntimeException(
                    'The FS PBX Nginx site does not contain the expected default ACME server.'
                );
            }

            if ($updatedNginxConfig !== $nginxConfig) {
                $this->writeFile(self::NGINX_CONFIG, $updatedNginxConfig);
            }

            if (! $this->runProcess(['nginx', '-t'])) {
                if ($updatedNginxConfig !== $nginxConfig) {
                    $this->writeFile(self::NGINX_CONFIG, $nginxConfig);
                    echo "Nginx validation failed. Restored the existing site configuration.\n";
                }

                return false;
            }

            if ($dehydratedConfig !== null && $updatedDehydratedConfig !== $dehydratedConfig) {
                $this->writeFile(self::DEHYDRATED_CONFIG, $updatedDehydratedConfig);
                echo 'Updated Dehydrated WELLKNOWN to '.self::WELLKNOWN.".\n";
            }

            if ($updatedNginxConfig !== $nginxConfig) {
                echo "Updated the Nginx ACME configuration.\n";
            }

            // A reload cannot reliably replace the existing 127.0.0.1:80
            // socket with the new wildcard listeners. Restart Nginx so the
            // public IPv4 and IPv6 port-80 sockets are actually opened.
            echo "Restarting Nginx to activate the ACME listener...\n";
            if (! $this->runProcess(['systemctl', 'restart', 'nginx'])) {
                echo "Nginx could not be restarted.\n";

                return false;
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }

    private function readDehydratedConfig(): ?string
    {
        if (! is_file(self::DEHYDRATED_CONFIG)) {
            echo "Dehydrated is not configured. Skipping its ACME webroot migration.\n";

            return null;
        }

        $config = file_get_contents(self::DEHYDRATED_CONFIG);

        if ($config === false) {
            throw new \RuntimeException('Unable to read '.self::DEHYDRATED_CONFIG.'.');
        }

        return $config;
    }

    private function usesManagedWellKnownDirectory(string $config): bool
    {
        return preg_match(
            '/^\s*WELLKNOWN\s*=\s*["\']?(?:'.preg_quote(self::LEGACY_WELLKNOWN, '/').'|'.preg_quote(self::WELLKNOWN, '/').')\/?["\']?\s*$/m',
            $config
        ) === 1;
    }

    private function ensureWellKnownDirectory(): void
    {
        if (! is_dir(self::WELLKNOWN) && ! mkdir(self::WELLKNOWN, 0755, true) && ! is_dir(self::WELLKNOWN)) {
            throw new \RuntimeException('Unable to create '.self::WELLKNOWN.'.');
        }

        @chown(self::WELLKNOWN, 'www-data');
        @chgrp(self::WELLKNOWN, 'www-data');
        @chmod(self::WELLKNOWN, 0755);
    }

    private function moveLegacyChallengeFiles(): void
    {
        if (! is_dir(self::LEGACY_WELLKNOWN)) {
            return;
        }

        $entries = new \FilesystemIterator(self::LEGACY_WELLKNOWN, \FilesystemIterator::SKIP_DOTS);
        $moved = 0;

        foreach ($entries as $entry) {
            $destination = self::WELLKNOWN.'/'.$entry->getFilename();

            if (file_exists($destination)) {
                continue;
            }

            if (! rename($entry->getPathname(), $destination)) {
                throw new \RuntimeException('Unable to move '.$entry->getPathname().' to the new ACME webroot.');
            }

            $moved++;
        }

        if ($moved > 0) {
            echo "Moved {$moved} existing ACME challenge item(s) to the shared webroot.\n";
        }
    }

    private function updateDehydratedConfig(string $config): string
    {
        return preg_replace_callback(
            '/^(\s*WELLKNOWN\s*=\s*)["\']?'.preg_quote(self::LEGACY_WELLKNOWN, '/').'\/?["\']?\s*$/m',
            fn (array $matches) => $matches[1].self::WELLKNOWN,
            $config
        ) ?? $config;
    }

    private function readNginxConfig(): string
    {
        if (! is_file(self::NGINX_CONFIG)) {
            throw new \RuntimeException('Nginx site configuration was not found at '.self::NGINX_CONFIG.'.');
        }

        $config = file_get_contents(self::NGINX_CONFIG);

        if ($config === false) {
            throw new \RuntimeException('Unable to read '.self::NGINX_CONFIG.'.');
        }

        return $config;
    }

    private function updateNginxConfig(string $config): string
    {
        $pattern = '#(?P<indent>^[ \t]*)location\s+\^~\s+/\.well-known/acme-challenge/?\s*\{(?P<body>[^{}]*)\}#m';

        $updated = preg_replace_callback($pattern, function (array $matches) {
            if (! preg_match('#\balias\s+'.preg_quote(self::LEGACY_WELLKNOWN, '#').'/?\s*;#', $matches['body'])) {
                return $matches[0];
            }

            $indent = $matches['indent'];
            $inner = $indent.'        ';

            return $indent."location ^~ /.well-known/acme-challenge/ {\n"
                .$inner."default_type \"text/plain\";\n"
                .$inner."auth_basic \"off\";\n"
                .$inner."root /var/www/fspbx/public;\n"
                .$inner.'try_files $uri =404;'."\n"
                .$indent.'}';
        }, $config) ?? $config;

        if (! $this->hasDefaultAcmeServer($updated)) {
            $updated = $this->defaultAcmeServer()."\n\n".ltrim($updated);
        }

        return $updated;
    }

    private function hasDefaultAcmeServer(string $config): bool
    {
        return preg_match(
            '#server\s*\{\s*'
            .'listen\s+80\s+default_server;\s*'
            .'listen\s+\[::\]:80\s+default_server;\s*'
            .'server_name\s+_;\s*'
            .'location\s+\^~\s+/\.well-known/acme-challenge/\s*\{'
            .'[^{}]*\broot\s+/var/www/fspbx/public/?\s*;'
            .'[^{}]*\btry_files\s+\$uri\s+=404\s*;'
            .'[^{}]*\}\s*'
            .'location\s+/\s*\{'
            .'[^{}]*\breturn\s+301\s+https://\$host\$request_uri\s*;'
            .'[^{}]*\}\s*'
            .'\}#s',
            $config
        ) === 1;
    }

    private function defaultAcmeServer(): string
    {
        return <<<'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /var/www/fspbx/public;
        try_files $uri =404;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}
NGINX;
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
