<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

class ConfigureModule extends Command
{
    protected $signature = 'modules:configure {module} {--uninstall} {--update-only : Run only the installed module update hook}';
    protected $description = 'Run the installed module lifecycle commands in a freshly booted application';

    public function handle(): int
    {
        try {
            $name = $this->argument('module');
            $module = Module::findOrFail($name);
            if ($this->option('uninstall')) {
                $this->optionalHook("module:uninstall-{$name}");
                return self::SUCCESS;
            }

            if ($this->option('update-only')) {
                $this->optionalHook("module:update-{$name}");
                return self::SUCCESS;
            }

            if (($module->get('migration')['automatic'] ?? true) !== false) {
                $this->checkedCall('module:migrate', ['module' => $name, '--force' => true]);
            }
            $this->checkedCall('module:seed', ['module' => $name, '--force' => true]);
            $this->optionalHook("module:install-{$name}");
            $this->optionalHook("module:update-{$name}");

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }

    private function optionalHook(string $command): void
    {
        if ($this->getApplication()->has($command)) {
            $this->checkedCall($command);
        }
    }

    private function checkedCall(string $command, array $arguments = []): void
    {
        if ($this->call($command, $arguments) !== self::SUCCESS) {
            throw new RuntimeException("Module configuration failed: {$command}");
        }
    }
}
