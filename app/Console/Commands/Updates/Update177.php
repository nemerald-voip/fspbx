<?php

namespace App\Console\Commands\Updates;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\MenuItem;
use App\Services\DialplanService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class Update177
{
    private const CALL_BLOCK_DIALPLAN_APP_UUID = 'b1b31930-d0ee-4395-a891-04df94599f1f';
    private const OLD_CALL_BLOCK_SCRIPT = 'app.lua call_block';
    private const NEW_CALL_BLOCK_SCRIPT = 'lua/call_block.lua';

    private string $freeswitchSource = '/usr/src/freeswitch';
    private string $autoloadConfigPath = '/etc/freeswitch/autoload_configs';

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        $this->updateCallBlockMenuItems();
        $this->updateCallBlockDialplanScriptPath();

        echo "== FS PBX: mod_hiredis setup ==\n";

        try {
            $this->writeHiredisConfig();
            $this->ensureModuleLoadLine();

            if ($this->moduleExists()) {
                echo "mod_hiredis is already loaded.\n";
                $this->reloadXml();
                return true;
            }

            $this->installHiredisDependency();
            $this->compileModule();
            $this->reloadXml();
            $this->reloadModule();

            if ($this->moduleExists()) {
                echo "mod_hiredis loaded successfully.\n";
            } else {
                echo "WARNING: mod_hiredis could not be verified after setup. Call block will fall back to DB lookup.\n";
            }
        } catch (\Throwable $e) {
            echo "WARNING: mod_hiredis setup encountered an error: {$e->getMessage()}\n";
            echo "Call block will fall back to DB lookup if Redis is unavailable.\n";
        }

        return true;
    }

    private function updateCallBlockMenuItems(): void
    {
        try {
            $updatedMenuItems = MenuItem::query()
                ->where('menu_item_title', 'Call Block')
                ->where('menu_item_link', '/app/call_block/call_block.php')
                ->update([
                    'menu_item_link' => '/call-blocks',
                ]);

            echo $updatedMenuItems === 0
                ? "No Call Block menu items required updating.\n"
                : "Updated {$updatedMenuItems} Call Block menu item(s).\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to update Call Block menu item: {$e->getMessage()}\n";
        }
    }

    private function updateCallBlockDialplanScriptPath(): void
    {
        try {
            $dialplans = Dialplans::query()
                ->where('app_uuid', self::CALL_BLOCK_DIALPLAN_APP_UUID)
                ->get(['dialplan_uuid', 'dialplan_context', 'dialplan_xml']);

            $updatedDialplans = 0;
            $contextsToClear = collect();

            foreach ($dialplans as $dialplan) {
                $xml = (string) $dialplan->dialplan_xml;
                $updatedXml = str_replace(self::OLD_CALL_BLOCK_SCRIPT, self::NEW_CALL_BLOCK_SCRIPT, $xml);

                if ($updatedXml === $xml) {
                    continue;
                }

                $dialplan->forceFill([
                    'dialplan_xml' => $updatedXml,
                    'update_date' => now(),
                ])->save();

                $updatedDialplans++;
                $contextsToClear->push($dialplan->dialplan_context);
            }

            $updatedDetails = 0;
            $dialplanUuids = $dialplans->pluck('dialplan_uuid')->filter()->values();

            if ($dialplanUuids->isNotEmpty()) {
                $updatedDetails = DialplanDetails::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->where('dialplan_detail_tag', 'action')
                    ->where('dialplan_detail_type', 'lua')
                    ->where('dialplan_detail_data', self::OLD_CALL_BLOCK_SCRIPT)
                    ->update([
                        'dialplan_detail_data' => self::NEW_CALL_BLOCK_SCRIPT,
                        'update_date' => now(),
                    ]);

                if ($updatedDetails > 0) {
                    $contextsToClear = $contextsToClear
                        ->merge($dialplans->pluck('dialplan_context'));
                }
            }

            $contextsToClear
                ->filter()
                ->unique()
                ->each(fn ($context) => app(DialplanService::class)->clearDialplanCache($context));

            echo "Updated {$updatedDialplans} Call Block dialplan XML record(s).\n";
            echo "Updated {$updatedDetails} Call Block dialplan detail record(s).\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to update Call Block dialplan XML: {$e->getMessage()}\n";
        }
    }

    private function writeHiredisConfig(): void
    {
        if (! File::isDirectory($this->autoloadConfigPath)) {
            echo "WARNING: {$this->autoloadConfigPath} does not exist. Skipping hiredis.conf.xml write.\n";
            return;
        }

        $configPath = $this->autoloadConfigPath . '/hiredis.conf.xml';
        $redis = $this->redisConnection();
        $passwordLine = $redis['password'] === null
            ? ''
            : '          <param name="password" value="' . $this->xml($redis['password']) . '"/>' . PHP_EOL;

        $contents = <<<XML
<configuration name="hiredis.conf" description="mod_hiredis">
  <profiles>
    <profile name="default">
      <connections>
        <connection name="primary">
          <param name="hostname" value="{$this->xml($redis['host'])}"/>
          <param name="port" value="{$this->xml((string) $redis['port'])}"/>
          <param name="timeout_ms" value="500"/>
{$passwordLine}        </connection>
      </connections>
      <params>
        <param name="ignore-connect-fail" value="true"/>
        <param name="ignore-error" value="true"/>
      </params>
    </profile>
  </profiles>
</configuration>
XML;

        try {
            File::put($configPath, $contents);
            echo "Wrote {$configPath}.\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to write {$configPath}: {$e->getMessage()}\n";
        }
    }

    private function ensureModuleLoadLine(): void
    {
        $modulesPath = $this->autoloadConfigPath . '/modules.conf.xml';

        if (! File::exists($modulesPath)) {
            echo "WARNING: {$modulesPath} not found. Skipping module autoload update.\n";
            return;
        }

        $contents = File::get($modulesPath);

        if (str_contains($contents, 'mod_hiredis')) {
            echo "modules.conf.xml already loads mod_hiredis.\n";
            return;
        }

        $loadLine = "\t\t<load module=\"mod_hiredis\"/>\n";

        if (str_contains($contents, '<load module="mod_memcache"/>')) {
            $contents = str_replace(
                "\t\t<load module=\"mod_memcache\"/>\n",
                "\t\t<load module=\"mod_memcache\"/>\n" . $loadLine,
                $contents
            );
        } elseif (str_contains($contents, '<load module="mod_commands"/>')) {
            $contents = str_replace(
                "\t\t<load module=\"mod_commands\"/>\n",
                "\t\t<load module=\"mod_commands\"/>\n" . $loadLine,
                $contents
            );
        } else {
            echo "WARNING: Could not find an insertion point in modules.conf.xml for mod_hiredis.\n";
            return;
        }

        try {
            File::put($modulesPath, $contents);
            echo "Added mod_hiredis to modules.conf.xml.\n";
        } catch (\Throwable $e) {
            echo "WARNING: Unable to update {$modulesPath}: {$e->getMessage()}\n";
        }
    }

    private function installHiredisDependency(): void
    {
        if ($this->packageInstalled('libhiredis-dev')) {
            echo "libhiredis-dev is already installed.\n";
            return;
        }

        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "WARNING: libhiredis-dev is missing, but app:update is not running as root. Skipping apt install.\n";
            return;
        }

        $this->run(['apt-get', 'install', '-y', 'libhiredis-dev'], 600);
    }

    private function compileModule(): void
    {
        if (! File::isDirectory($this->freeswitchSource)) {
            echo "WARNING: {$this->freeswitchSource} not found. Skipping mod_hiredis compile.\n";
            return;
        }

        if (! $this->sourceHasHiredisModule()) {
            echo "WARNING: FreeSWITCH source does not contain applications/mod_hiredis. Skipping compile.\n";
            return;
        }

        $this->enableSourceModule();
        $this->refreshBuildConfigurationIfNeeded();

        if (! $this->hiredisMakefileCanBuild()) {
            echo "WARNING: mod_hiredis build configuration is still disabled after refresh. Skipping compile.\n";
            return;
        }

        $process = new Process(['make', 'mod_hiredis-install'], $this->freeswitchSource);
        $process->setTimeout(1200);
        $this->runProcess($process, false);
    }

    private function sourceHasHiredisModule(): bool
    {
        return File::isDirectory($this->freeswitchSource . '/src/mod/applications/mod_hiredis')
            || File::isDirectory($this->freeswitchSource . '/applications/mod_hiredis')
            || str_contains((string) @file_get_contents($this->freeswitchSource . '/modules.conf'), 'applications/mod_hiredis');
    }

    private function enableSourceModule(): void
    {
        $modulesPath = $this->freeswitchSource . '/modules.conf';

        if (! File::exists($modulesPath)) {
            echo "WARNING: {$modulesPath} not found. Cannot enable mod_hiredis in the build tree.\n";
            return;
        }

        $contents = File::get($modulesPath);

        if (preg_match('/^applications\/mod_hiredis$/m', $contents)) {
            echo "FreeSWITCH source modules.conf already enables mod_hiredis.\n";
            return;
        }

        $updated = preg_replace('/^#applications\/mod_hiredis$/m', 'applications/mod_hiredis', $contents, 1, $count);

        if ($count === 0 || $updated === null) {
            echo "WARNING: Could not enable applications/mod_hiredis in {$modulesPath}.\n";
            return;
        }

        File::put($modulesPath, $updated);
        echo "Enabled applications/mod_hiredis in FreeSWITCH source modules.conf.\n";
    }

    private function refreshBuildConfigurationIfNeeded(): void
    {
        if ($this->hiredisMakefileCanBuild()) {
            return;
        }

        if (! File::exists($this->freeswitchSource . '/config.status')) {
            echo "WARNING: FreeSWITCH config.status not found. Cannot refresh build configuration for mod_hiredis.\n";
            return;
        }

        echo "Refreshing FreeSWITCH build configuration so libhiredis is detected.\n";

        $recheck = new Process(['./config.status', '--recheck'], $this->freeswitchSource);
        $recheck->setTimeout(1200);

        if (! $this->runProcess($recheck, false)) {
            return;
        }

        $configStatus = new Process(['./config.status'], $this->freeswitchSource);
        $configStatus->setTimeout(1200);
        $this->runProcess($configStatus, false);
    }

    private function hiredisMakefileCanBuild(): bool
    {
        $makefile = $this->freeswitchSource . '/src/mod/applications/mod_hiredis/Makefile';

        if (! File::exists($makefile)) {
            return false;
        }

        $contents = File::get($makefile);

        return str_contains($contents, 'mod_LTLIBRARIES = mod_hiredis.la')
            && ! preg_match('/^(install|all): error$/m', $contents);
    }

    private function moduleExists(): bool
    {
        $process = new Process(['fs_cli', '-x', 'module_exists mod_hiredis']);
        $process->setTimeout(20);

        if (! $this->runProcess($process, false, true, false)) {
            return false;
        }

        return str_contains(strtolower(trim($process->getOutput() . $process->getErrorOutput())), 'true');
    }

    private function reloadXml(): void
    {
        $this->run(['fs_cli', '-x', 'reloadxml'], 30);
    }

    private function reloadModule(): void
    {
        $this->run(['fs_cli', '-x', 'load mod_hiredis'], 30);
        $this->run(['fs_cli', '-x', 'reload mod_hiredis'], 30);
    }

    private function packageInstalled(string $package): bool
    {
        $process = new Process(['dpkg-query', '-W', '-f=${Status}', $package]);
        $process->setTimeout(20);

        return $this->runProcess($process, false, false, false)
            && str_contains($process->getOutput(), 'install ok installed');
    }

    private function run(array $command, int $timeout): bool
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        return $this->runProcess($process, false);
    }

    private function runProcess(Process $process, bool $throw, bool $warn = true, bool $echoOutput = true): bool
    {
        $process->run(function ($type, $buffer) use ($echoOutput) {
            if ($echoOutput) {
                echo $buffer;
            }
        });

        if ($process->isSuccessful()) {
            return true;
        }

        $command = $process->getCommandLine();
        if ($warn) {
            echo "WARNING: Command failed: {$command}\n";
        }

        if ($throw) {
            throw new \RuntimeException("Command failed: {$command}");
        }

        return false;
    }

    private function redisConnection(): array
    {
        $redis = config('database.redis.default', []);

        $host = $redis['host'] ?? '127.0.0.1';
        $port = $redis['port'] ?? '6379';
        $password = $redis['password'] ?? null;

        if (! empty($redis['url'])) {
            $url = parse_url($redis['url']);

            if (is_array($url)) {
                $host = $url['host'] ?? $host;
                $port = $url['port'] ?? $port;
                $password = $url['pass'] ?? $password;
            }
        }

        if ($password === '' || strtolower((string) $password) === 'null') {
            $password = null;
        }

        return [
            'host' => (string) $host,
            'port' => (string) $port,
            'password' => $password === null ? null : (string) $password,
        ];
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
