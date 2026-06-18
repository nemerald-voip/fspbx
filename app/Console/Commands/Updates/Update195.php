<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class Update195
{

    private const SUPERVISOR_SOURCE = 'install/ai-receptionist-agent.conf';
    private const SUPERVISOR_TARGET = '/etc/supervisor/conf.d/ai-receptionist-agent.conf';
    private const AGENT_DIR = 'resources/ai-receptionist-agent';
    private const AGENT_VENV_DIR = '/opt/fspbx/ai-receptionist-agent/.venv';
    private const AGENT_TOKEN_KEY = 'AI_RECEPTIONIST_AGENT_TOKEN';
    private const AGENT_ENV_DEFAULTS = [
        'AI_RECEPTIONIST_CONTROLLER_URL' => 'http://127.0.0.1:8097/calls',
        'AI_RECEPTIONIST_HEALTH_HOST' => '127.0.0.1',
        'AI_RECEPTIONIST_HEALTH_PORT' => '8097',
    ];

    public function apply(): bool
    {
        $this->ensureAgentToken();
        $this->ensureAgentEnvDefaults();
        $this->runSeeder();
        $this->ensureAiReceptionistRouteCollectedFieldsColumn();
        $this->ensureAIReceptionistsMenuItem();
        $this->removeSourceRuntimeArtifacts();
        $this->ensurePythonEnvironment();
        $this->installSupervisorConfig();

        echo "Update 1.9.5 completed successfully.\n";
        return true;
    }

    private function runSeeder(): void
    {
        echo "Running DatabaseSeeder for AI Receptionist permissions...\n";
        $exitCode = Artisan::call('db:seed', [
            '--force' => true,
            '--no-interaction' => true,
        ]);
        echo Artisan::output();

        if ($exitCode !== 0) {
            echo "DatabaseSeeder returned exit code {$exitCode}; app:update will run seeding again later.\n";
        }
    }

    private function ensureAIReceptionistsMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping AI Receptionists menu item.\n";
            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found in menu '{$menu->menu_name}'; skipping AI Receptionists menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
            ->where($this->aiReceptionistsMenuMatcher())
            ->first();
        $menuItemIsUnderApplications = (bool) $menuItem;

        if (! $menuItem) {
            $menuItem = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where($this->aiReceptionistsMenuMatcher())
                ->first();
        }

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => 'AI Receptionists',
                'menu_item_link' => '/ai-receptionists',
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItemIsUnderApplications && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $applicationsItem),
            ])->save();

            echo "AI Receptionists menu item already exists; ensured it is under Applications with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_title' => 'AI Receptionists',
                'menu_item_link' => '/ai-receptionists',
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $applicationsItem),
            ]);

            echo "Added AI Receptionists menu item under Applications.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
    }

    private function aiReceptionistsMenuMatcher(): callable
    {
        return function ($query) {
            $query->where('menu_item_link', '/ai-receptionists')
                ->orWhereIn('menu_item_title', ['AI Receptionist', 'AI Receptionists']);
        };
    }

    private function nextMenuItemOrder(Menu $menu, MenuItem $parentItem): int
    {
        return ((int) MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $parentItem->menu_item_uuid)
            ->max('menu_item_order')) + 1;
    }

    private function ensureMenuLanguage(Menu $menu, MenuItem $menuItem): void
    {
        $language = MenuLanguage::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $menuItem->menu_item_uuid)
            ->where('menu_language', 'en-us')
            ->first();

        if ($language) {
            if ($language->menu_item_title !== $menuItem->menu_item_title) {
                $language->forceFill([
                    'menu_item_title' => $menuItem->menu_item_title,
                ])->save();
            }

            return;
        }

        MenuLanguage::query()->create([
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => $menuItem->menu_item_title,
        ]);
    }

    private function ensureMenuItemGroups(Menu $menu, MenuItem $menuItem, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; AI Receptionists menu access not created for it.\n";
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                echo "AI Receptionists menu access already exists for group '{$groupName}'.\n";
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            echo "Granted AI Receptionists menu access to group '{$groupName}'.\n";
        }
    }

    private function ensureAiReceptionistRouteCollectedFieldsColumn(): void
    {
        if (! Schema::hasTable('ai_receptionist_routes')) {
            echo "AI Receptionist routes table was not found; skipping collected fields column.\n";
            return;
        }

        if (Schema::hasColumn('ai_receptionist_routes', 'collected_fields')) {
            echo "AI Receptionist route collected fields column already exists.\n";
            return;
        }

        Schema::table('ai_receptionist_routes', function (Blueprint $table) {
            $table->json('collected_fields')->nullable()->after('match_phrases');
        });

        echo "Added AI Receptionist route collected fields column.\n";
    }

    private function installSupervisorConfig(): void
    {
        $source = base_path(self::SUPERVISOR_SOURCE);
        $target = self::SUPERVISOR_TARGET;

        if (! File::exists($source)) {
            echo "AI Receptionist supervisor template not found at {$source}; skipping.\n";
            return;
        }

        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "Not running as root; skipping supervisor config install for AI Receptionist agent.\n";
            echo "Copy {$source} to {$target} after configuring the Python environment.\n";
            return;
        }

        try {
            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);
            $this->run(['chmod', '0644', $target], true);
            $this->run(['chown', 'root:root', $target], true);
            $this->run(['supervisorctl', 'reread'], true);
            $this->run(['supervisorctl', 'update'], true);
            echo "AI Receptionist supervisor config installed. Start the agent from System Settings after configuration is complete.\n";
        } catch (Throwable $exception) {
            echo "Could not install AI Receptionist supervisor config: {$exception->getMessage()}\n";
        }
    }

    private function ensurePythonEnvironment(): void
    {
        $agentDir = base_path(self::AGENT_DIR);
        $venvDir = self::AGENT_VENV_DIR;
        $venvPython = "{$venvDir}/bin/python";
        $venvPip = "{$venvDir}/bin/pip";
        $requirements = "{$agentDir}/requirements.txt";

        if (! File::exists($requirements)) {
            echo "AI Receptionist requirements file not found at {$requirements}; skipping Python dependency install.\n";
            return;
        }

        if (! File::exists($venvPython) && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "Not running as root; skipping AI Receptionist Python virtual environment creation at {$venvDir}.\n";
            echo "Install python3-venv and create the virtual environment manually, then start the agent from System Settings.\n";
            return;
        }

        File::ensureDirectoryExists(dirname($venvDir));

        if (! File::exists($venvPython)) {
            echo "Creating AI Receptionist Python virtual environment...\n";
            $this->run(['python3', '-m', 'venv', $venvDir], true);
        }

        if (! File::exists($venvPip) && $this->canInstallPackages()) {
            echo "AI Receptionist Python virtual environment is incomplete; installing python3-venv with apt-get...\n";
            $this->run(['apt-get', 'update'], true, 300);
            $this->run(['apt-get', 'install', '-y', 'python3-venv'], true, 600);
            File::deleteDirectory($venvDir);
            echo "Retrying AI Receptionist Python virtual environment creation...\n";
            $this->run(['python3', '-m', 'venv', $venvDir], true);
        }

        if (! File::exists($venvPip)) {
            echo "AI Receptionist virtual environment is not available; skipping Python dependency install. Install python3-venv and rerun the update or create {$venvDir} manually.\n";
            return;
        }

        echo "Installing AI Receptionist Python dependencies...\n";
        $this->run([$venvPip, 'install', '-r', $requirements], true, 600);
    }

    private function removeSourceRuntimeArtifacts(): void
    {
        $agentDir = base_path(self::AGENT_DIR);
        $paths = [
            "{$agentDir}/.venv",
            "{$agentDir}/agent/__pycache__",
        ];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            try {
                File::deleteDirectory($path);
                echo "Removed AI Receptionist generated runtime artifact: {$path}\n";
            } catch (Throwable $exception) {
                echo "Could not remove AI Receptionist generated runtime artifact {$path}: {$exception->getMessage()}\n";
            }
        }
    }

    private function ensureAgentToken(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            echo "WARNING: .env not found at {$envPath}. Skipping AI Receptionist agent token setup.\n";
            return;
        }

        $env = File::get($envPath);
        $existingToken = $this->getEnvValue($env, self::AGENT_TOKEN_KEY);

        if (filled($existingToken)) {
            echo "AI Receptionist agent token is already configured.\n";
            return;
        }

        $token = bin2hex(random_bytes(32));
        $env = $this->setEnvValue($env, self::AGENT_TOKEN_KEY, $token, "\n\n### FS PBX - AI Receptionist\n");

        File::put($envPath, $env);
        echo "Generated AI Receptionist agent token in .env.\n";

        $exitCode = Artisan::call('config:cache');
        echo Artisan::output();

        if ($exitCode !== 0) {
            echo "Config cache returned exit code {$exitCode}; cache Laravel config manually if the agent token is not detected.\n";
        }
    }

    private function ensureAgentEnvDefaults(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            echo "WARNING: .env not found at {$envPath}. Skipping AI Receptionist environment defaults.\n";
            return;
        }

        $env = File::get($envPath);
        $changed = false;

        foreach (self::AGENT_ENV_DEFAULTS as $key => $value) {
            if (filled($this->getEnvValue($env, $key))) {
                continue;
            }

            $env = $this->setEnvValue($env, $key, $value, "\n\n### FS PBX - AI Receptionist\n");
            $changed = true;
        }

        if (! $changed) {
            echo "AI Receptionist environment defaults are already configured.\n";
            return;
        }

        File::put($envPath, $env);
        echo "Added AI Receptionist environment defaults to .env.\n";

        $exitCode = Artisan::call('config:cache');
        echo Artisan::output();

        if ($exitCode !== 0) {
            echo "Config cache returned exit code {$exitCode}; cache Laravel config manually if AI Receptionist environment defaults are not detected.\n";
        }
    }

    private function canInstallPackages(): bool
    {
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            return false;
        }

        return File::exists('/usr/bin/apt-get') || File::exists('/bin/apt-get');
    }

    private function getEnvValue(string $env, string $key): ?string
    {
        if (! preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)\s*$/m', $env, $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    private function setEnvValue(string $env, string $key, string $value, string $blockHeader): string
    {
        $line = "{$key}={$value}";

        $env = preg_replace(
            '/^\s*#\s*' . preg_quote($key, '/') . '\s*=.*$/m',
            $line,
            $env,
            1
        );

        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $env)) {
            $env = preg_replace(
                '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m',
                $line,
                $env,
                1
            );

            return rtrim($this->removeDuplicateEnvKeys($env, $key)) . "\n";
        }

        return rtrim($env) . $blockHeader . $line . "\n";
    }

    private function removeDuplicateEnvKeys(string $env, string $key): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $env);
        $seen = false;
        $result = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                if ($seen) {
                    continue;
                }

                $seen = true;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    private function run(array $command, bool $allowFailure = false, int $timeout = 60): void
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful() && ! $allowFailure) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if ($process->getOutput()) {
            echo $process->getOutput();
        }

        if (! $process->isSuccessful() && $allowFailure) {
            echo trim($process->getErrorOutput() ?: $process->getOutput()) . "\n";
        }
    }
}
