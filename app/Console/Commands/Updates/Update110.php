<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Artisan;

class Update110
{
    protected string $source = 'install/fs-cdr-service.conf';
    protected string $target = '/etc/supervisor/conf.d/fs-cdr-service.conf';
    protected string $program = 'fs-cdr-service'; // must match [program:...] in your conf

    // Regex to match the legacy cron lines (spaces normalized)
    // Matches any token after xml_cdr_import.php (e.g., 100 abcdef), and any redirection.
    protected string $xmlCdrCronPattern =
    '#^\s*\*\s+\*\s+\*\s+\*\s+\*\s+cd\s+/var/www/fspbx;\s*/usr/bin/php\s+/var/www/fspbx/public/app/xml_cdr/xml_cdr_import\.php\b.*$#i';


    public function apply()
    {
        // /var/www/fspbx/public/app/switch/resources/scripts/app/ring_groups/index.lua
        echo "[Update110] Starting...\n";

        // Preflight: root check (most failures are permissions)
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "[Update110] WARNING: This step needs root for /etc write, chown, and supervisorctl.\n";
            echo "[Update110] Run: sudo php artisan app:update\n";
        }

        try {

            // 0) Disable legacy cron job lines
            $this->disableLegacyCron();

            // 1) Verify source exists
            $sourcePath = base_path($this->source);
            if (!File::exists($sourcePath)) {
                throw new \RuntimeException("Source supervisor config not found: {$sourcePath}");
            }
            echo "[Update110] Source: {$sourcePath}\n";

            // 2) Ensure target dir exists
            $targetDir = dirname($this->target);
            if (!File::exists($targetDir)) {
                echo "[Update110] Creating dir: {$targetDir}\n";
                $this->run(['mkdir', '-p', $targetDir]);
            }

            // 4) Copy new file
            echo "[Update110] Copying to: {$this->target}\n";
            File::copy($sourcePath, $this->target);

            // 5) Set permissions/owner
            echo "[Update110] chmod 0644\n";
            $this->run(['chmod', '0644', $this->target]);

            echo "[Update110] chown root:root\n";
            $this->run(['chown', 'root:root', $this->target]); // needs root

            // 6) Reload supervisor
            echo "[Update110] supervisorctl reread\n";
            $this->run(['supervisorctl', 'reread']);

            echo "[Update110] supervisorctl update\n";
            $this->run(['supervisorctl', 'update']);

            // 7) Restart just this program (if present)
            echo "[Update110] supervisorctl restart {$this->program}\n";
            $this->run(['supervisorctl', 'restart', $this->program], true);

            echo "[Update110] Done.\n";

            // 7b) Restart Horizon queue workers (graceful first, then Supervisor fallback)
            echo "[Update110] Restarting Horizon queue workers...\n";
            $this->restartHorizon();

            $result = $this->runMenuUpdate();

            return true;
        } catch (\Exception $e) {
            echo "Error applying the update: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Run the artisan command to update the FS PBX menu.
     *
     * @return int Exit code of the Artisan call
     */
    protected function runMenuUpdate(): int
    {
        echo "Running menu:update (menu:create-fspbx --update)...\n";
        $exitCode = Artisan::call('menu:create-fspbx', ['--update' => true]);
        $output   = Artisan::output();
        echo $output;

        if ($exitCode !== 0) {
            echo "Error: Menu update command failed with exit code $exitCode.\n";
        } else {
            echo "Menu update completed successfully.\n";
        }

        return $exitCode;
    }

    /**
     * Gracefully restart Horizon workers. If supervised, `horizon:terminate` will
     * trigger a respawn. Also try common Supervisor names as a fallback.
     * Non-fatal if Horizon/Supervisor aren't present.
     */
    protected function restartHorizon(): void
    {
        // 1) Graceful terminate (lets running jobs finish)
        $artisan = base_path('artisan');
        if (file_exists($artisan)) {
            echo "[Update110] php artisan horizon:terminate\n";
            $this->run(['php', $artisan, 'horizon:terminate'], true);
        } else {
            echo "[Update110] WARNING: artisan not found; skipping graceful terminate.\n";
        }

        // 2) Fallback: try restarting common Supervisor program names
        // Your system shows `horizon:horizon_00` in `supervisorctl status`,
        // so include that plus common names.
        $candidates = [
            'horizon:*',
            'horizon',
            'laravel-horizon',
            'horizon:horizon_00',
        ];

        foreach ($candidates as $name) {
            echo "[Update110] supervisorctl restart {$name}\n";
            $this->run(['supervisorctl', 'restart', $name], true); // ignore failure if not defined
        }
    }


    /**
     * Disable legacy xml_cdr_import.php crontab lines by commenting them out.
     * Idempotent: won’t double-comment.
     */
/**
 * Disable any cron lines containing xml_cdr_import.php by commenting them out.
 * This version is safe, simple, and avoids regex fragility or bash -lc hangs.
 */
protected function disableLegacyCron(): void
{
    echo "[Update110] Checking crontab for xml_cdr_import.php entries...\n";

    // 1) Read existing crontab (ignore failure if none)
    $list = $this->runCapture(['crontab', '-l'], true);
    $original = trim($list['stdout'] ?? '');

    if ($original === '') {
        echo "[Update110] No crontab for this user.\n";
        return;
    }

    $lines   = preg_split('/\R/', $original);
    $changed = false;
    $out     = [];

    foreach ($lines as $line) {
        $trim = ltrim($line);

        // Skip empty lines or already-commented lines
        if ($trim === '' || str_starts_with($trim, '#')) {
            $out[] = $line;
            continue;
        }

        // Comment out any line containing xml_cdr_import.php
        if (stripos($line, 'xml_cdr_import.php') !== false) {
            $out[] = '# DISABLED BY FS PBX UPDATE 1.10 — ' . $line;
            $changed = true;
            continue;
        }

        // Keep all other lines intact
        $out[] = $line;
    }

    if (!$changed) {
        echo "[Update110] No xml_cdr_import.php lines found (or already disabled).\n";
        return;
    }

    // 2) Backup existing crontab
    $backupDir = '/var/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $backupPath = $backupDir . '/cron-before-update110-' . date('Ymd-His') . '.txt';
    file_put_contents($backupPath, $original);

    echo "[Update110] Saved crontab backup: {$backupPath}\n";

    // 3) Write updated crontab
    $newCrontab = implode(PHP_EOL, $out) . PHP_EOL;
    $this->runShellWithInput('crontab -', $newCrontab);

    echo "[Update110] Successfully disabled xml_cdr_import.php cron lines.\n";
}

    /**
     * Run a shell command; return ['success'=>bool,'stdout'=>string,'stderr'=>string].
     */
    protected function runCapture(array $cmd, bool $ignoreFailure = false): array
    {
        $proc = new Process($cmd);
        $proc->setTimeout(60);
        $proc->run();

        $ok = $proc->isSuccessful();
        if (!$ok && !$ignoreFailure) {
            throw new \RuntimeException(sprintf(
                "Command failed: %s\nExit: %s\nStdErr: %s",
                implode(' ', $cmd),
                $proc->getExitCode(),
                trim($proc->getErrorOutput())
            ));
        }

        return [
            'success' => $ok,
            'stdout'  => $proc->getOutput(),
            'stderr'  => $proc->getErrorOutput(),
        ];
    }

    /**
     * Pipe $input to $cmd (e.g. "crontab -").
     */
    protected function runShellWithInput(string $cmd, string $input): void
    {
        $proc = Process::fromShellCommandline($cmd);
        $proc->setTimeout(60);
        $proc->setInput($input);
        $proc->run();

        if (!$proc->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                "Command failed: %s\nExit: %s\nStdErr: %s",
                $cmd,
                $proc->getExitCode(),
                trim($proc->getErrorOutput())
            ));
        }
    }


    /**
     * Run a shell command and throw on failure (unless $ignoreFailure = true).
     */
    protected function run(array $cmd, bool $ignoreFailure = false): void
    {
        $proc = new Process($cmd);
        $proc->setTimeout(60);
        $proc->run();

        if (!$proc->isSuccessful() && !$ignoreFailure) {
            throw new \RuntimeException(sprintf(
                "Command failed: %s\nExit: %s\nStdErr: %s",
                implode(' ', $cmd),
                $proc->getExitCode(),
                trim($proc->getErrorOutput())
            ));
        }
    }
}
