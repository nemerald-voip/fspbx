<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;
use Throwable;

class Update212
{
    private const VERSION = '2.1.2';
    private const NGINX_CONFIGS = [
        '/etc/nginx/sites-available/fspbx.conf',
        '/etc/nginx/sites-enabled/fspbx.conf',
    ];
    private const MARKER = '# Legacy FusionPBX pages that now live in the FS PBX UI';

    // Legacy module directory => FS PBX page that replaced it.
    private const REDIRECTS = [
        'pin_numbers' => 'pin-numbers',
        'streams' => 'streams',
        'database_transactions' => 'database-transactions',
        'email_templates' => 'email-templates',
        'vars' => 'vars',
        'modules' => 'modules',
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
                echo "Nginx already redirects the retired legacy pages, or has no /login.php block to anchor on. No change.\n";

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
     * Add the redirect locations after every "location = /login.php" block.
     * Returns the config unchanged when already applied or when there is no anchor.
     */
    public function updateNginxConfig(string $config): string
    {
        if (str_contains($config, self::MARKER)) {
            return $config;
        }

        $updated = preg_replace_callback(
            '/^([ \t]*)location\s*=\s*\/login\.php\s*\{[^}]*\}[ \t]*\R/m',
            fn (array $match) => $match[0]."\n".$this->redirectBlock($match[1]),
            $config
        );

        return $updated ?? $config;
    }

    private function redirectBlock(string $indent): string
    {
        $inner = $indent.str_repeat(' ', 8);
        $block = $indent.self::MARKER."\n";

        foreach (self::REDIRECTS as $legacy => $target) {
            $block .= $indent.'location ~ ^/app/'.$legacy."/ {\n";
            $block .= $inner.'return 301 https://$host/'.$target.";\n";
            $block .= $indent."}\n";
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
