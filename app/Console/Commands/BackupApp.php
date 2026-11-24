<?php

namespace App\Console\Commands;

use App\Models\DefaultSettings;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupApp extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Back up FS PBX files and the PostgreSQL database';

    public function handle(): int
    {
        $previousMask = umask(0077);
        $lock = null;
        $work = null;
        try {
            $root = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_subcategory', 'backup_path')
                ->where('default_setting_enabled', true)->value('default_setting_value') ?: '/var/backups/fspbx';
            if (!str_starts_with($root, '/') || rtrim($root, '/') === '') {
                throw new RuntimeException('Choose an absolute backup directory other than /.');
            }
            $this->directory("$root/postgresql");
            $root = realpath($root);
            $lock = fopen("$root/.backup.lock", 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Could not lock the backup directory. Check for another running backup.');
            }

            $date = date('Y-m-d');
            $dump = "$root/postgresql/fspbx_pgsql_$date.sql";
            $archive = "$root/backup_$date.tgz";
            $work = "$root/.building-".bin2hex(random_bytes(8));
            $this->directory($work);

            $db = DB::connection()->getConfig();
            if ($db['driver'] !== 'pgsql') {
                throw new RuntimeException('FS PBX backup requires PostgreSQL.');
            }
            $this->info('Dumping PostgreSQL...');
            $this->runTool(['pg_dump', '-Fc', '--no-password', '--host='.$db['host'], '--port='.$db['port'],
                '--username='.$db['username'], '--file='.$work.'/db.partial', '--dbname='.$db['database']],
                ['PGPASSWORD' => $db['password']]);
            if (!rename("$work/db.partial", $dump)) {
                throw new RuntimeException('Could not publish the standalone database dump.');
            }

            $app = '/var/www/fspbx';
            if (!is_file("$app/artisan") || realpath($app) !== realpath(base_path())) {
                throw new RuntimeException("The running FS PBX installation must be accessible at $app.");
            }
            try {
                $dotenv = Dotenv::parse(file_get_contents("$app/.env"));
            } catch (\Throwable) {
                throw new RuntimeException('Unable to read and parse the source .env.');
            }
            $key = $dotenv['APP_KEY'] ?? '';
            $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            if (is_link("$app/.env") || !$bytes || !Encrypter::supported($bytes, config('app.cipher'))
                || !hash_equals((string) config('app.key'), $key)) {
                throw new RuntimeException('The regular .env file must contain the active application encryption key.');
            }
            if (!is_dir("$app/storage/app") || !is_file("$app/config/version.php")) {
                throw new RuntimeException('Missing application storage or release information.');
            }
            $media = ['/var/lib/freeswitch/storage', '/var/lib/freeswitch/recordings'];
            $paths = [
                '/usr/share/freeswitch', '/etc/freeswitch', ...$media, '/var/lib/freeswitch/images',
                '/usr/share/fusionpbx/templates/provision', '/usr/share/fspbx/templates/provision',
                '/etc/fusionpbx', '/etc/nginx/sites-available', '/etc/nginx/ssl',
                '/etc/dehydrated', '/etc/letsencrypt', '/etc/iptables',
            ];
            $paths = array_values(array_filter($paths, 'is_dir'));
            foreach ([$app, ...$paths] as $path) {
                $source = realpath($path);
                if ($source === $root || str_starts_with($source.'/', $root.'/') || str_starts_with($root.'/', $source.'/')) {
                    throw new RuntimeException('Choose a backup directory outside the PBX source directories.');
                }
            }
            $manifest = [
                'format' => 1,
                'release' => config('version.release'),
                'app' => ltrim($app, '/'),
                'dump' => ltrim($dump, '/'),
                'cluster' => DB::scalar("SELECT CASE WHEN has_function_privilege('pg_control_system()', 'EXECUTE')
                    THEN (SELECT system_identifier::text FROM pg_control_system()) END"),
                'cipher' => config('app.cipher'),
                'key_hash' => hash('sha256', (string) config('app.key')),
                'media' => array_values(array_map(fn ($path) => ltrim($path, '/'), array_filter($media, 'is_dir'))),
                'modules' => array_map(fn ($file) => basename(dirname($file)), glob("$app/Modules/*/module.json") ?: []),
            ];
            foreach (array_diff($media, $paths) as $missing) {
                $this->warn("$missing is absent. Verify any external media storage has its own backup.");
            }
            if (file_put_contents("$work/fspbx-migration.json", json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Could not write migration metadata.');
            }

            $this->info("Packaging $archive ...");
            $this->package($work, $app, '/', [ltrim($dump, '/'), ...array_map(fn ($path) => ltrim($path, '/'), $paths)]);
            if (!rename("$work/archive.partial", $archive)) {
                throw new RuntimeException('Could not publish the completed archive.');
            }

            foreach (glob("$root/backup_????-??-??.tgz") ?: [] as $older) {
                if ($older !== $archive && filemtime($older) < time() - 3 * 86400 && !unlink($older)) {
                    throw new RuntimeException("Could not remove old archive $older.");
                }
            }
            foreach (glob("$root/postgresql/fspbx_pgsql_????-??-??.sql") ?: [] as $standalone) {
                if (!unlink($standalone)) {
                    throw new RuntimeException("Could not remove standalone dump $standalone.");
                }
            }
            $this->info("Backup complete: $archive");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            if ($work !== null) {
                foreach (['db.partial', 'archive.partial', 'fspbx-migration.json'] as $name) {
                    if (is_file("$work/$name")) {
                        unlink("$work/$name");
                    }
                }
                if (is_dir($work)) {
                    rmdir($work);
                }
            }
            if (is_resource($lock)) {
                fclose($lock);
            }
            umask($previousMask);
        }
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException("Could not create $path.");
        }
    }

    private function package(string $work, string $app, string $filesystem, array $members): void
    {
        $command = ['tar', '--create', '--gzip', '--file='.$work.'/archive.partial', '--hard-dereference',
            '--transform=flags=r;s|^\./|var/www/fspbx/|;s|^\.$|var/www/fspbx|',
            '--exclude=.git', '--exclude=node_modules', '--exclude=./vendor',
            '--exclude=./storage/logs', '--exclude=./storage/framework',
            '--exclude=usr/share/freeswitch/sounds/music/default/*'];
        foreach (['callie', 'june', 'mario'] as $voice) {
            $command[] = "--exclude=usr/share/freeswitch/sounds/*/$voice/*";
        }
        // Enter the app root to archive its contents even when the install path is a symlink.
        $this->runTool([...$command, '-C', $work, 'fspbx-migration.json', '-C', $filesystem,
            ...$members, '-C', $app, '.']);
    }

    private function runTool(array $arguments, array $environment = []): void
    {
        $process = new Process($arguments, null, $environment);
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException($arguments[0].' failed: '.trim($process->getErrorOutput()));
        }
    }
}
