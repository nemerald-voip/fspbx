<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Services\Pcap\PcapService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class Update206
{
    private const VERSION = '2.0.6';
    private const CAPTURE_SCRIPT = '/usr/local/sbin/fspbx-pcap-capture';
    private const SUPERVISOR_CONFIGS = [
        'install/fspbx-pcap-capture.conf' => '/etc/supervisor/conf.d/fspbx-pcap-capture.conf',
        'install/fs-esl-listener-pcap.conf' => '/etc/supervisor/conf.d/fs-esl-listener-pcap.conf',
    ];

    public function getSupervisorProgramsToRestart(): array
    {
        return ['fspbx-pcap-capture', 'fs-esl-listener-pcap'];
    }

    public function apply(): bool
    {
        try {
            $this->ensureMenuItem();
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }

        // Host changes are best-effort: the PCAPs page explains what is missing.
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            echo "PCAP capture setup needs root; skipped. Run: sudo php artisan app:update\n";
        } else {
            $this->installPackages();
            $this->installCaptureService();
        }

        echo "Update " . self::VERSION . " completed successfully.\n";
        return true;
    }

    private function ensureMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();
        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping PCAPs menu item.\n";
            return;
        }

        $parent = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Status')
            ->whereNull('menu_item_parent_uuid')
            ->first();
        if (! $parent) {
            echo "Status menu item was not found; skipping PCAPs menu item.\n";
            return;
        }

        $item = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', '/pcaps')
            ->first();

        if (! $item) {
            $item = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $parent->menu_item_uuid,
                'menu_item_title' => 'PCAPs',
                'menu_item_link' => '/pcaps',
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $parent->menu_item_uuid)
                    ->max('menu_item_order')) + 1,
            ]);
        }

        MenuLanguage::query()->firstOrCreate([
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $item->menu_item_uuid,
            'menu_language' => 'en-us',
        ], [
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_item_title' => 'PCAPs',
        ]);

        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                continue;
            }

            MenuItemGroup::query()->firstOrCreate([
                'menu_item_uuid' => $item->menu_item_uuid,
                'group_uuid' => $group->group_uuid,
            ], [
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'group_name' => $groupName,
            ]);
        }

        echo "Ensured the PCAPs menu item.\n";
    }

    private function installPackages(): void
    {
        $missing = app(PcapService::class)->missingTools();
        if (! $missing) {
            echo "PCAP capture tools are already installed.\n";
            return;
        }

        echo "Installing PCAP capture tools (tcpdump, tshark)...\n";
        $process = new Process(['apt-get', 'install', '-y', '--no-install-recommends', 'tcpdump', 'tshark'], null, [
            'DEBIAN_FRONTEND' => 'noninteractive',
        ], null, 900);
        $process->run();

        $missing = app(PcapService::class)->missingTools();
        if ($missing) {
            echo "Could not install: " . implode(', ', $missing) . ". Install them with: apt-get install tcpdump tshark\n";
            echo trim($process->getErrorOutput()) . "\n";
            return;
        }

        echo "Installed PCAP capture tools.\n";
    }

    private function installCaptureService(): void
    {
        try {
            $spool = PcapService::BASE_DIR . '/spool';
            File::ensureDirectoryExists($spool, 0770);
            $this->run(['chown', 'www-data:www-data', PcapService::BASE_DIR, $spool]);
            $this->run(['chmod', '0770', PcapService::BASE_DIR, $spool]);

            // Create the PCAP folders as www-data so a root-run scheduler can't own them.
            foreach ([storage_path('app/pcaps'), storage_path('app/pcap-tmp')] as $dir) {
                File::ensureDirectoryExists($dir, 0770);
                $this->run(['chown', '-R', 'www-data:www-data', $dir]);
            }

            // Root runs this script, so it lives outside the web-writable tree.
            File::copy(base_path('install/fspbx-pcap-capture.sh'), self::CAPTURE_SCRIPT);
            $this->run(['chown', 'root:root', self::CAPTURE_SCRIPT]);
            $this->run(['chmod', '0755', self::CAPTURE_SCRIPT]);
            echo "Installed " . self::CAPTURE_SCRIPT . ".\n";

            foreach (self::SUPERVISOR_CONFIGS as $source => $target) {
                File::ensureDirectoryExists(dirname($target));
                File::copy(base_path($source), $target);
                $this->run(['chown', 'root:root', $target]);
                $this->run(['chmod', '0644', $target]);
                echo "Installed {$target}.\n";
            }

            $this->run(['supervisorctl', 'reread']);
            $this->run(['supervisorctl', 'update']);
            echo "Supervisor loaded the PCAP capture programs.\n";
        } catch (Throwable $exception) {
            echo "PCAP capture service setup failed: {$exception->getMessage()}\n";
        }
    }

    private function run(array $command): void
    {
        $process = new Process($command, null, null, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(implode(' ', $command) . ': ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
