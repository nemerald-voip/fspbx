<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;
use Throwable;

class Update215
{
    private const VERSION = '2.1.5';
    private const NGINX_CONFIGS = [
        '/etc/nginx/sites-available/fspbx.conf',
        '/etc/nginx/sites-enabled/fspbx.conf',
    ];
    private const HELPER_SOURCE = 'install/fspbx-apply-nginx-https.sh';
    private const HELPER_PATH = '/usr/local/sbin/fspbx-apply-nginx-https';
    private const SUDOERS_FILE = '/etc/sudoers.d/fspbx-nginx-https';
    private const SUDOERS_RULE = 'www-data ALL=(ALL) NOPASSWD: /usr/local/sbin/fspbx-apply-nginx-https';

    // The two TLS directives of the HTTPS server block. They move into the managed snippet,
    // whose "off" content is exactly these lines, so nothing changes until the setting is turned on.
    private const TLS_DIRECTIVES = "        ssl_protocols           TLSv1.2;\n        ssl_ciphers             HIGH:!ADH:!MD5:!aNULL;\n";
    private const INCLUDE_LINE = "        include snippets/fspbx-https.conf;\n";

    public function apply(): bool
    {
        try {
            $path = $this->nginxConfigPath();

            if ($path === null) {
                echo "FS PBX Nginx site not found. Skipping the HTTPS setting.\n";

                return true;
            }

            $this->installHelper();
            $this->installSudoersRule();

            $config = file_get_contents($path);

            if ($config === false) {
                throw new \RuntimeException('Unable to read '.$path.'.');
            }

            $updated = $this->updateNginxConfig($config);

            if ($updated !== $config) {
                $this->writeFile($path.'.bak-'.self::VERSION, $config);
                $this->writeFile($path, $updated);
            }

            // Writes the default ("off") snippet only when there is none.
            if (! $this->runProcess([self::HELPER_PATH, 'init'])) {
                $this->restore($path, $config, $updated);
                echo "Could not write the HTTPS snippet. Restored the existing site configuration.\n";

                // Not essential: do not block the rest of the update.
                return true;
            }

            if (! $this->runProcess(['nginx', '-t'])) {
                $this->restore($path, $config, $updated);
                echo "Nginx validation failed. Restored the existing site configuration.\n";

                return true;
            }

            if (! $this->runProcess(['systemctl', 'reload', 'nginx'])) {
                echo "Nginx could not be reloaded. The change will apply after the next restart.\n";
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }

    /**
     * Replace the two TLS directives of the HTTPS server block with an include of the managed
     * snippet. A site file that has already been changed, or that does not have the directives
     * exactly as the install template does (a customised file), is left as it is.
     */
    public function updateNginxConfig(string $config): string
    {
        if (str_contains($config, trim(self::INCLUDE_LINE))) {
            return $config;
        }

        if (substr_count($config, self::TLS_DIRECTIVES) !== 1) {
            return $config;
        }

        return str_replace(self::TLS_DIRECTIVES, self::INCLUDE_LINE, $config);
    }

    private function installHelper(): void
    {
        $source = base_path(self::HELPER_SOURCE);

        if (! is_file($source)) {
            throw new \RuntimeException('Missing '.$source.'.');
        }

        if (! copy($source, self::HELPER_PATH)) {
            throw new \RuntimeException('Unable to copy the helper to '.self::HELPER_PATH.'.');
        }

        chmod(self::HELPER_PATH, 0755);
    }

    private function installSudoersRule(): void
    {
        $existing = is_file(self::SUDOERS_FILE) ? file_get_contents(self::SUDOERS_FILE) : '';

        if (is_string($existing) && str_contains($existing, self::SUDOERS_RULE)) {
            return;
        }

        $temporary = self::SUDOERS_FILE.'.new';
        $this->writeFile($temporary, self::SUDOERS_RULE."\n");
        chmod($temporary, 0440);

        // A broken sudoers file locks everyone out of sudo: only move it into place if it parses.
        if (! $this->runProcess(['visudo', '-cf', $temporary])) {
            @unlink($temporary);

            throw new \RuntimeException('The sudoers rule did not validate. It was not installed.');
        }

        rename($temporary, self::SUDOERS_FILE);
    }

    private function restore(string $path, string $original, string $updated): void
    {
        if ($updated !== $original) {
            $this->writeFile($path, $original);
        }
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
