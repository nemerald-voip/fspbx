<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class Update150
{
    private string $fspbxRoot = '/var/www/fspbx';
    private int $reverbPort = 8095;

    public function apply()
    {
        echo "== FS PBX: Reverb setup ==\n";

        $this->upgradePhpIfNeeded();

        $this->ensureEnv();
        $this->installNginx();
        $this->installSupervisor();

        // supervisor steps should be best-effort: don't brick the update 
        $this->run(['supervisorctl', 'reread'], false);
        $this->run(['supervisorctl', 'update'], false);
        $this->run(['supervisorctl', 'restart', 'reverb'], false);

        echo "Reverb setup complete.\n";
        return true;
    }

    private function upgradePhpIfNeeded(): void
    {
        // Only run on servers still on PHP 8.1 / < 8.4
        $current = PHP_VERSION; // version of the CLI running this artisan command
        if (version_compare($current, '8.4.0', '>=')) {
            echo "PHP is already {$current}. Skipping PHP upgrade script.\n";
            return;
        }

        // Must be root (script requires root)
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "ERROR: PHP upgrade requires root. Run the update as root:\n";
            echo "  sudo php artisan app:update\n";
            exit(1);
        }

        $script = '/var/www/fspbx/install/upgrade_php_8_1_to_8_4.sh';
        if (!file_exists($script)) {
            echo "ERROR: PHP upgrade script not found: {$script}\n";
            exit(1);
        }

        // Ensure executable (best effort)
        @chmod($script, 0755);

        echo "Running PHP upgrade script: {$script}\n";
        // Give it a large timeout (apt + compile can take a while)
        $this->run(['bash', $script], true, 3600);

        echo "PHP upgrade script finished.\n";
    }


    private function ensureEnv(): void
    {
        $envPath = $this->fspbxRoot . '/.env';

        if (!File::exists($envPath)) {
            echo "WARNING: .env not found at {$envPath}. Skipping env setup.\n";
            return;
        }

        $env = File::get($envPath);

        // Generate credentials once if missing
        $appId     = $this->getEnvValue($env, 'REVERB_APP_ID') ?: (string) random_int(100000, 999999);
        $appKey    = $this->getEnvValue($env, 'REVERB_APP_KEY') ?: Str::lower(Str::random(20));
        $appSecret = $this->getEnvValue($env, 'REVERB_APP_SECRET') ?: Str::lower(Str::random(32));

        $updates = [
            // Ensure broadcasting uses Reverb
            'BROADCAST_CONNECTION' => 'reverb',

            // Reverb credentials
            'REVERB_APP_ID'        => $appId,
            'REVERB_APP_KEY'       => $appKey,
            'REVERB_APP_SECRET'    => $appSecret,

            // Bind Reverb locally
            'REVERB_SERVER_HOST'   => '127.0.0.1',
            'REVERB_SERVER_PORT'   => (string) $this->reverbPort,

            // Laravel talks to local Reverb
            'REVERB_HOST'          => '127.0.0.1',
            'REVERB_PORT'          => (string) $this->reverbPort,
            'REVERB_SCHEME'        => 'http',

            // Frontend: only key is required if you compute host/scheme at runtime
            'VITE_REVERB_APP_KEY'  => $appKey,
        ];

        $env = $this->applyEnvUpdates($env, $updates);
        File::put($envPath, $env);

        echo "Updated .env with Reverb settings.\n";
    }

    private function installNginx(): void
    {
        $src = $this->fspbxRoot . '/install/nginx_reverb.conf';
        $dst = '/etc/nginx/snippets/fspbx-reverb.conf';

        if (!File::exists($src)) {
            echo "WARNING: Missing {$src}. Skipping nginx snippet install.\n";
            return;
        }

        try {
            File::put($dst, File::get($src));
            echo "Copied nginx snippet to {$dst}\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to write nginx snippet {$dst}: {$e->getMessage()}\n";
            return;
        }

        $site = $this->findNginxSiteConfig();
        if (!$site) {
            echo "WARNING: Could not find SSL nginx site config in /etc/nginx/sites-available/ with root /var/www/fspbx/public.\n";
            echo "         Please add `include snippets/fspbx-reverb.conf;` inside the SSL server block.\n";
            return;
        }

        $conf = File::get($site);

        if (str_contains($conf, 'snippets/fspbx-reverb.conf')) {
            echo "Nginx SSL site already includes Reverb snippet: {$site}\n";
        } else {
            // Find the SSL server block and inject after its opening "server {"
            $pattern = '/server\s*\{(?:(?!\}\s*server|\}\s*$).)*?(listen\s+443\b.*\bssl\b|ssl_certificate\b)/is';

            if (preg_match($pattern, $conf, $m, PREG_OFFSET_CAPTURE)) {
                $blockStartPos = $m[0][1];

                // Find the position of the first "server {" within the matched block (relative)
                $serverOpenPos = stripos(substr($conf, $blockStartPos, 200), 'server');
                if ($serverOpenPos === false) {
                    echo "WARNING: Failed to locate 'server {' for SSL block injection in {$site}\n";
                } else {
                    $absServerPos = $blockStartPos + $serverOpenPos;

                    // Replace only the first "server {" at that position
                    $before = substr($conf, 0, $absServerPos);
                    $after  = substr($conf, $absServerPos);

                    // Inject include line right after server {
                    $after = preg_replace(
                        '/server\s*\{/',
                        "server {\n    include snippets/fspbx-reverb.conf;\n",
                        $after,
                        1
                    );

                    $new = $before . $after;

                    File::put($site, $new);
                    echo "Injected snippet include into SSL server block: {$site}\n";
                }
            } else {
                echo "WARNING: Could not identify SSL server block to inject into in {$site}\n";
                echo "         Please add `include snippets/fspbx-reverb.conf;` manually.\n";
            }
        }

        $this->run(['nginx', '-t'], false);
        $this->run(['systemctl', 'reload', 'nginx'], false);
    }

    private function installSupervisor(): void
    {
        $src = $this->fspbxRoot . '/install/reverb.conf';
        $dst = '/etc/supervisor/conf.d/reverb.conf';

        if (!File::exists($src)) {
            echo "WARNING: Missing {$src}. Skipping supervisor install.\n";
            return;
        }

        try {
            File::put($dst, File::get($src));
            echo "Copied supervisor config to {$dst}\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to write supervisor config {$dst}: {$e->getMessage()}\n";
            return;
        }

        $this->run(['supervisorctl', 'reread'], false);
        $this->run(['supervisorctl', 'update'], false);
        $this->run(['supervisorctl', 'restart', 'reverb'], false);
    }

    private function findNginxSiteConfig(): ?string
    {
        $candidates = glob('/etc/nginx/sites-available/*') ?: [];

        foreach ($candidates as $path) {
            if (!is_file($path)) continue;
            $conf = @file_get_contents($path);
            if (!$conf) continue;

            // Must match FS PBX public root
            $hasRoot = str_contains($conf, 'root /var/www/fspbx/public');
            if (!$hasRoot) continue;

            // Must be SSL-enabled
            $hasSsl =
                preg_match('/listen\s+443\b.*\bssl\b/i', $conf) ||
                str_contains($conf, 'ssl_certificate') ||
                str_contains($conf, 'ssl_certificate_key');

            if (!$hasSsl) continue;

            return $path;
        }

        return null;
    }

    private function run(array $cmd, bool $mustSucceed, int $timeoutSeconds = 30): bool
    {
        try {
            $p = new \Symfony\Component\Process\Process($cmd);
            $p->setTimeout($timeoutSeconds);

            // stream output so clients can see progress
            $p->run(function ($type, $buffer) {
                echo $buffer;
            });

            if ($p->isSuccessful()) {
                return true;
            }

            $msg = trim($p->getErrorOutput()) ?: trim($p->getOutput());
            echo "ERROR: Command failed: " . implode(' ', $cmd) . "\n{$msg}\n";

            if ($mustSucceed) exit(1);
            return false;
        } catch (\Throwable $e) {
            echo "ERROR: Process error (" . implode(' ', $cmd) . "): {$e->getMessage()}\n";
            if ($mustSucceed) exit(1);
            return false;
        }
    }


    private function getEnvValue(string $env, string $key): ?string
    {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)\s*$/m', $env, $m)) {
            $val = trim($m[1]);
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            return $val;
        }
        return null;
    }

    private function applyEnvUpdates(string $env, array $updates): string
    {
        $blockHeader = "\n\n### FS PBX - Laravel Reverb\n";
        $append = '';

        foreach ($updates as $key => $value) {

            // 1) If commented version exists, replace it (first occurrence only)
            $env = preg_replace(
                '/^\s*#\s*' . preg_quote($key, '/') . '\s*=.*$/m',
                $key . '=' . $value,
                $env,
                1
            );

            // 2) If key exists, set the FIRST occurrence to our value
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $env)) {
                $env = preg_replace(
                    '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m',
                    $key . '=' . $value,
                    $env,
                    1
                );

                // 3) Remove any later duplicates of the same key
                $env = $this->removeDuplicateEnvKeys($env, $key);

                continue;
            }

            // 4) Otherwise, append it
            $append .= $key . '=' . $value . "\n";
        }

        if ($append !== '') {
            $env = rtrim($env) . $blockHeader . $append;
        }

        return rtrim($env) . "\n";
    }

    private function removeDuplicateEnvKeys(string $env, string $key): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $env);
        $seen = false;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $line)) {
                if (!$seen) {
                    $seen = true;   // keep the first one
                } else {
                    $lines[$i] = null; // remove duplicates
                }
            }
        }

        $lines = array_values(array_filter($lines, fn($l) => $l !== null));
        return implode("\n", $lines);
    }
}
