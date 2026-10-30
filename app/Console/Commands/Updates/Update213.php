<?php

namespace App\Console\Commands\Updates;

use App\Services\DialplanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process as SystemProcess;
use Symfony\Component\Process\Process;
use Throwable;

class Update213
{
    private const DIALPLAN_CATALOG_PATH = 'app/dialplans/app_config.php';

    private const PICKUP_TEMPLATES = [
        '230_group-intercept.xml' => '15332e83-12f5-44d3-8472-633736eb4b9b',
        '290_intercept-ext.xml' => '2b7b2f82-edfe-4339-8cc5-7d0cf36e1e68',
    ];

    public function apply(): bool
    {
        // Retired system services must not keep retrying missing PHP scripts,
        // even if a later download or dialplan update needs to be retried.
        $this->removeLegacyQueueServices();

        try {
            $this->downloadDialplanFiles();
            $changes = $this->patchDialplans();
            echo "Updated {$changes} stock pickup dialplan(s).\n";
        } catch (Throwable $error) {
            echo "Error applying update 2.1.3: {$error->getMessage()}\n";
            return false;
        }

        // Repeat invalidation/reload on retries, including after DB replication.
        try {
            app(DialplanService::class)->clearDialplanCache('global');
            $this->reloadXml();
        } catch (Throwable $error) {
            echo "WARNING: Pickup dialplan reload failed: {$error->getMessage()}\n";
            echo "Continuing the application update. Clear the dialplan cache and run fs_cli -x reloadxml on this server.\n";
        }
        return true;
    }

    public function getSupervisorProgramsToRestart(): array
    {
        return ['fs-cdr-service'];
    }

    public function removeLegacyQueueServices(): bool
    {
        $complete = true;
        $removed = [];
        $reload = false;
        foreach (['email_queue.service', 'fax_queue.service'] as $unit) {
            try {
                $state = $this->queueServiceState($unit);
                $paths = [];
                foreach ($this->systemdUnitDirectories() as $directory) {
                    $paths[] = $directory.'/'.$unit;
                    $paths[] = $directory.'/'.$unit.'.d';
                    foreach (['*.wants', '*.requires'] as $dependency) {
                        $paths = array_merge($paths, glob($directory.'/'.$dependency.'/'.$unit) ?: []);
                    }
                }
                $paths = array_values(array_filter(array_unique($paths), fn ($path) => file_exists($path) || is_link($path)));
                if ($state['LoadState'] === 'not-found' && $state['ActiveState'] === 'inactive' && $paths === []) {
                    echo "{$unit}: already absent.\n";
                    continue;
                }

                // Explicitly stop first: deleting a unit file alone does not
                // cancel systemd's already-loaded Restart=always loop.
                if ($state['LoadState'] !== 'not-found' || $state['ActiveState'] !== 'inactive') {
                    $this->runQueueSystemctl(['stop', $unit]);
                    if (! in_array($state['LoadState'], ['masked', 'not-found'], true)) {
                        $this->runQueueSystemctl(['disable', $unit]);
                    }
                    // An inactive unit may already have been unloaded by stop
                    // or disable. reset-failed rejects those absent units.
                    $stopped = $this->queueServiceState($unit);
                    if ($stopped['ActiveState'] === 'failed') {
                        $this->runQueueSystemctl(['reset-failed', $unit]);
                    } elseif ($stopped['ActiveState'] !== 'inactive') {
                        throw new \RuntimeException("{$unit} did not stop ({$stopped['ActiveState']})");
                    }
                }

                $reload = true;
                foreach ($paths as $path) {
                    // disable may have removed an enablement link already.
                    if (is_link($path) || is_file($path)) {
                        if (! File::delete($path)) {
                            throw new \RuntimeException("Could not remove {$path}");
                        }
                    } elseif (is_dir($path) && ! File::deleteDirectory($path)) {
                        throw new \RuntimeException("Could not remove {$path}");
                    }
                }
                $removed[] = $unit;
            } catch (Throwable $error) {
                $complete = false;
                echo "WARNING: Could not fully remove {$unit}: {$error->getMessage()}\n";
            }
        }

        if ($reload) {
            try {
                $this->runQueueSystemctl(['daemon-reload']);
                foreach ($removed as $unit) {
                    $state = $this->queueServiceState($unit);
                    if ($state['LoadState'] !== 'not-found' || $state['ActiveState'] !== 'inactive') {
                        throw new \RuntimeException("{$unit} is still {$state['LoadState']}/{$state['ActiveState']}");
                    }
                    echo "{$unit}: stopped and removed.\n";
                }
            } catch (Throwable $error) {
                $complete = false;
                echo "WARNING: Legacy queue service removal could not be verified: {$error->getMessage()}\n";
            }
        }

        if (! $complete) {
            echo "Continuing the application update. Resolve the warning, then retry: php artisan tinker --execute='(new \\App\\Console\\Commands\\Updates\\Update213)->removeLegacyQueueServices();'\n";
        }
        return $complete;
    }

    protected function systemdUnitDirectories(): array
    {
        return ['/etc/systemd/system', '/run/systemd/system', '/usr/local/lib/systemd/system',
            '/usr/lib/systemd/system', '/lib/systemd/system'];
    }

    private function queueServiceState(string $unit): array
    {
        $result = SystemProcess::timeout(30)->run([
            'systemctl', 'show', '--property=LoadState', '--property=ActiveState', $unit,
        ]);
        $state = [];
        foreach (explode("\n", trim($result->output())) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $state[$key] = $value;
            }
        }
        if (! isset($state['LoadState'], $state['ActiveState'])
            || (! $result->successful() && $state['LoadState'] !== 'not-found')) {
            throw new \RuntimeException(trim($result->errorOutput()) ?: "Could not inspect {$unit}");
        }
        return $state;
    }

    private function runQueueSystemctl(array $arguments): void
    {
        SystemProcess::timeout(60)->run(['systemctl', ...$arguments])->throw();
    }

    private function downloadDialplanFiles(): void
    {
        // public/ is a separate repository. Existing installs need these
        // replacements explicitly, as with the earlier dialplan XML updates.
        $downloads = [];
        foreach (self::PICKUP_TEMPLATES as $fileName => $appUuid) {
            $path = 'app/dialplans/resources/switch/conf/dialplan/'.$fileName;
            $contents = $this->downloadFromMaster($path, function (string $contents) use ($fileName, $appUuid) {
                $previous = libxml_use_internal_errors(true);
                try {
                    $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
                    if ($xml === false || $xml->getName() !== 'extension'
                        || (string) $xml['app_uuid'] !== $appUuid || ! self::hasPatchedCondition($contents)) {
                        throw new \RuntimeException("Invalid or outdated pickup template: {$fileName}.");
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            });
            $downloads[public_path($path)] = $contents;
        }

        // Update211 removed dialplan.destination from the database. Also deploy
        // the corrected catalog so the legacy defaults importer cannot restore it.
        $catalog = $this->downloadFromMaster(self::DIALPLAN_CATALOG_PATH, function (string $catalog) {
            if (! str_starts_with(ltrim($catalog), '<?php')
                || ! str_contains($catalog, '742714e5-8cdf-32fd-462c-cbe7e3d655db')
                || str_contains($catalog, 'd3c5a59d-e6c0-4ded-ac79-9a7fcd9e1269')) {
                throw new \RuntimeException('Invalid or outdated dialplan catalog: '.self::DIALPLAN_CATALOG_PATH.'.');
            }
            // Check PHP syntax without executing downloaded code.
            token_get_all($catalog, TOKEN_PARSE);
        });
        $downloads[public_path(self::DIALPLAN_CATALOG_PATH)] = $catalog;

        // Validate every download before replacing any local file.
        foreach ($downloads as $path => $contents) {
            File::ensureDirectoryExists(dirname($path));
            File::replace($path, $contents);
            echo basename($path)." file downloaded and replaced successfully.\n";
        }
    }

    private function downloadFromMaster(string $path, callable $validate): string
    {
        try {
            $contents = Http::connectTimeout(10)->timeout(30)
                ->get('https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/'.$path)
                ->throw()->body();
            $validate($contents);
            return $contents;
        } catch (Throwable $error) {
            echo "Raw master download could not be used: {$error->getMessage()}\n";
            echo "Retrying {$path} through GitHub's contents API with ref=master.\n";
        }

        // Raw branch URLs can remain stale after a push. The contents endpoint
        // reads the same master branch without pinning this update to a commit.
        $file = Http::connectTimeout(10)->timeout(30)->withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'FS-PBX-Update213',
            'Cache-Control' => 'no-cache',
        ])->get('https://api.github.com/repos/nemerald-voip/fusionpbx/contents/'.$path, ['ref' => 'master'])
            ->throw()->json();
        if (! is_array($file) || ($file['type'] ?? null) !== 'file' || ($file['path'] ?? null) !== $path
            || ($file['encoding'] ?? null) !== 'base64' || ! is_string($file['content'] ?? null)) {
            throw new \RuntimeException("Invalid GitHub master contents response for {$path}.");
        }
        $contents = base64_decode($file['content'], true);
        if ($contents === false) {
            throw new \RuntimeException("Invalid GitHub master file encoding for {$path}.");
        }
        $validate($contents);
        return $contents;
    }

    public function patchDialplans(): int
    {
        return DB::transaction(function () {
            $changed = 0;
            $dialplans = DB::table('v_dialplans')->whereIn('app_uuid', array_values(self::PICKUP_TEMPLATES))->get();
            foreach ($dialplans as $dialplan) {
                $xml = (string) $dialplan->dialplan_xml;
                $updated = self::patchXml($xml);
                // Do not rewrite custom direct intercept actions or their rules.
                if ($updated === $xml && !str_contains($xml, 'intercept.lua')) {
                    continue;
                }
                $detailChanges = 0;
                $conditions = DB::table('v_dialplan_details')->where('dialplan_uuid', $dialplan->dialplan_uuid)
                    ->where('dialplan_detail_tag', 'condition')->where('dialplan_detail_type', '${sip_h_X-intercept_uuid}')
                    ->where('dialplan_detail_data', '^(.+)$')->get();
                // Only patch rules when the XML contains the matching stock
                // condition (old or already repaired). Custom XML wins.
                if ($updated !== $xml || self::hasPatchedCondition($xml)) {
                    foreach ($conditions as $condition) {
                        $actions = DB::table('v_dialplan_details')->where('dialplan_uuid', $dialplan->dialplan_uuid)
                            ->where('dialplan_detail_group', $condition->dialplan_detail_group)
                            ->where('dialplan_detail_tag', 'action');
                        if ((clone $actions)->count() !== 1) {
                            continue;
                        }
                        $detailChanges += $actions->where('dialplan_detail_type', 'intercept')->where('dialplan_detail_data', '$1')
                            ->update(['dialplan_detail_type' => 'lua', 'dialplan_detail_data' => 'intercept.lua', 'update_date' => now()]);
                    }
                }
                if ($updated !== $xml) {
                    DB::table('v_dialplans')->where('dialplan_uuid', $dialplan->dialplan_uuid)
                        ->update(['dialplan_xml' => $updated, 'update_date' => now()]);
                }
                $changed += (int) ($updated !== $xml || $detailChanges > 0);
            }
            return $changed;
        });
    }

    public static function patchXml(string $xml): string
    {
        return preg_replace_callback('/<condition\b[^>]*>\s*<action\b[^>]*\/\s*>\s*<\/condition>/s', function ($match) {
            $previous = libxml_use_internal_errors(true);
            try {
                $condition = simplexml_load_string($match[0], \SimpleXMLElement::class, LIBXML_NONET);
                if (!$condition || (string) $condition['field'] !== '${sip_h_X-intercept_uuid}'
                    || (string) $condition['expression'] !== '^(.+)$'
                    || (string) $condition->action['application'] !== 'intercept'
                    || (string) $condition->action['data'] !== '$1') {
                    return $match[0];
                }
                return preg_replace(['/(application\s*=\s*)(["\'])intercept\2/', '/(data\s*=\s*)(["\'])\$1\2/'],
                    ['$1"lua"', '$1"intercept.lua"'], $match[0]);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }, $xml) ?? $xml;
    }

    private static function hasPatchedCondition(string $xml): bool
    {
        // Reuse the exact matcher on a temporary reverse substitution.
        $old = str_replace('application="lua" data="intercept.lua"', 'application="intercept" data="$1"', $xml);
        return $old !== $xml && self::patchXml($old) !== $old;
    }

    protected function reloadXml(): void
    {
        $process = new Process(['fs_cli', '-x', 'reloadxml']);
        $process->setTimeout(30);
        $process->mustRun();
        if (str_starts_with(ltrim($process->getOutput()), '-ERR')) {
            throw new \RuntimeException(trim($process->getOutput()));
        }
    }
}
