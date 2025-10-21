<?php


namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class Update102
{
    /**
     * Apply update steps.
     */
    public function apply(): bool
    {
        $source = base_path('install/nginx_fspbx_internal.conf');
        $nginxAvailable = '/etc/nginx/sites-available';
        $nginxEnabled   = '/etc/nginx/sites-enabled';
        $targetName     = 'fspbx_internal.conf';
        $targetAvailable = rtrim($nginxAvailable, '/').'/'.$targetName;
        $targetEnabled   = rtrim($nginxEnabled, '/').'/'.$targetName;

        echo "==> FS PBX Update 1.0.2: Installing Nginx internal vhost\n";

        // 0) Preconditions
        if (!File::exists($source)) {
            echo "ERROR: Source file not found: {$source}\n";
            return false;
        }
        if (!is_dir($nginxAvailable) || !is_dir($nginxEnabled)) {
            echo "ERROR: Nginx sites-available/enabled directories not found.\n";
            echo "  Expected:\n  - {$nginxAvailable}\n  - {$nginxEnabled}\n";
            return false;
        }

        // 1) Write/overwrite sites-available target (with backup)
        if (File::exists($targetAvailable)) {
            $backup = $targetAvailable.'.bak-'.date('Ymd-His');
            echo "Backing up existing: {$targetAvailable} -> {$backup}\n";
            if (!@copy($targetAvailable, $backup)) {
                echo "ERROR: Failed to back up {$targetAvailable}\n";
                return false;
            }
        }

        echo "Copying {$source} -> {$targetAvailable}\n";
        if (!@copy($source, $targetAvailable)) {
            echo "ERROR: Failed to copy internal vhost to sites-available.\n";
            return false;
        }

        // 2) Ensure permissions sane (root:root 0644 typically)
        @chown($targetAvailable, 'root');
        @chgrp($targetAvailable, 'root');
        @chmod($targetAvailable, 0644);

        // 3) Enable site via symlink
        if (is_link($targetEnabled) || File::exists($targetEnabled)) {
            echo "Removing existing enabled link/file: {$targetEnabled}\n";
            @unlink($targetEnabled);
        }

        echo "Creating symlink: {$targetEnabled} -> {$targetAvailable}\n";
        if (!@symlink($targetAvailable, $targetEnabled)) {
            echo "ERROR: Failed to create symlink in sites-enabled.\n";
            return false;
        }

        // 4) Test Nginx config
        echo "Testing Nginx configuration: nginx -t\n";
        if (!$this->runOk(['bash','-lc','nginx -t'])) {
            echo "ERROR: nginx -t failed. Restoring previous state.\n";
            // Best effort rollback: remove new symlink
            @unlink($targetEnabled);
            return false;
        }

        // 5) Reload Nginx
        echo "Reloading Nginx...\n";
        // Try systemd first, then service
        if (!$this->runOk(['bash','-lc','systemctl reload nginx || service nginx reload'])) {
            echo "ERROR: Failed to reload Nginx.\n";
            return false;
        }

        echo "✅ Update 1.0.2 completed: internal vhost installed & Nginx reloaded.\n";
        return true;
    }

    private function runOk(array $cmd): bool
    {
        $p = new Process($cmd, null, null, null, 60);
        $p->run(function ($type, $buffer) {
            echo $buffer;
        });
        return $p->isSuccessful();
    }
}
