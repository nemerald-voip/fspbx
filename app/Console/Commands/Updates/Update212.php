<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class Update212
{
    private const VERSION = '2.1.2';
    private const TOGGLE_DIALPLAN_UUID = '6f1b8a52-3c0e-4d7b-9a61-2f4e8c7d5b13';
    private const TOGGLE_APP_UUID = 'b8e3d1f4-6a2c-4e95-8d07-4c1f9e2a7b60';
    private const TOGGLE_EXPRESSION = '^flag(\d+)$';
    private const TOGGLE_ACTION = 'lua/flag_toggle.lua';
    private const FLAG_BLF_STARTUP_LINE = '<param name="startup-script" value="lua/flag_blf.lua"/>';

    public function apply(): bool
    {
        try {
            $this->ensureMenuItem();
            $this->ensureToggleDialplan();
            $this->patchFlagBlfStartupConfiguration();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();
        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Flags menu item.\n";
            return;
        }

        $parent = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();
        if (! $parent) {
            echo "Applications menu item was not found; skipping Flags menu item.\n";
            return;
        }

        $item = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', '/flags')
            ->first();

        if (! $item) {
            $item = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $parent->menu_item_uuid,
                'menu_item_title' => 'Flags',
                'menu_item_link' => '/flags',
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
            'menu_item_title' => 'Flags',
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

        echo "Ensured the Flags menu item.\n";
    }

    /**
     * Global dialplan: dialing flag<number> (usually a BLF key) toggles a manual flag.
     */
    private function ensureToggleDialplan(): void
    {
        if (Dialplans::query()->whereKey(self::TOGGLE_DIALPLAN_UUID)->exists()) {
            echo "Flag toggle dialplan already exists.\n";
            return;
        }

        $expression = self::TOGGLE_EXPRESSION;
        $action = self::TOGGLE_ACTION;
        $uuid = self::TOGGLE_DIALPLAN_UUID;

        $xml = <<<XML
<extension name="toggle-flag" continue="false" uuid="{$uuid}">
    <condition field="destination_number" expression="{$expression}">
        <action application="lua" data="{$action}"/>
    </condition>
</extension>
XML;

        Dialplans::query()->create([
            'dialplan_uuid' => $uuid,
            'app_uuid' => self::TOGGLE_APP_UUID,
            'domain_uuid' => null,
            'hostname' => null,
            'dialplan_context' => 'global',
            'dialplan_name' => 'toggle-flag',
            'dialplan_number' => 'flag[number]',
            'dialplan_destination' => null,
            'dialplan_continue' => 'false',
            'dialplan_xml' => $xml,
            'dialplan_order' => 509,
            'dialplan_enabled' => 'true',
            'dialplan_description' => 'Switch a manual Flag on or off.',
        ]);

        foreach ([
            ['condition', 'destination_number', $expression, 5],
            ['action', 'lua', $action, 10],
        ] as [$tag, $type, $data, $order]) {
            DialplanDetails::query()->create([
                'dialplan_detail_uuid' => (string) Str::uuid(),
                'domain_uuid' => null,
                'dialplan_uuid' => $uuid,
                'dialplan_detail_tag' => $tag,
                'dialplan_detail_type' => $type,
                'dialplan_detail_data' => $data,
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => $order,
                'dialplan_detail_enabled' => 'true',
            ]);
        }

        // Global dialplans are included in every account context.
        FusionCache::clear('dialplan.*');

        echo "Added the flag toggle dialplan.\n";
    }

    private function patchFlagBlfStartupConfiguration(): void
    {
        $autoloadDirectory = $this->autoloadConfigDirectory();
        if (! $autoloadDirectory) {
            echo "WARNING: The FreeSWITCH configuration directory was not found; the flag BLF startup service was not configured.\n";
            return;
        }

        $destination = $autoloadDirectory . '/lua.conf.xml';
        if (! File::exists($destination)) {
            echo "WARNING: {$destination} was not found; the flag BLF startup service was not configured.\n";
            return;
        }

        $contents = File::get($destination);
        $linePattern = '/^[ \t]*<param\b(?=[^>]*\bname=["\']startup-script["\'])(?=[^>]*\bvalue=["\']lua\/flag_blf\.lua["\'])[^>]*\/>[ \t]*\R?/mi';

        if (preg_match_all($linePattern, $contents) === 1) {
            echo "{$destination} already starts the flag BLF service.\n";
            return;
        }

        $withoutDuplicates = preg_replace($linePattern, '', $contents) ?? $contents;
        $startupBlock = "    <!-- FS PBX: Flag BLF daemon -->\n    " . self::FLAG_BLF_STARTUP_LINE . "\n";
        $updated = preg_replace('/^([ \t]*<\/settings>[ \t]*\R?)/mi', $startupBlock . '$1', $withoutDuplicates, 1, $count);

        if ($count !== 1 || $updated === null || ! $this->validXmlContents($updated, 'lua.conf')) {
            echo "WARNING: Unable to add the flag BLF startup service to {$destination}.\n";
            return;
        }

        if (! is_writable($destination)) {
            echo "WARNING: {$destination} is not writable by this user; the flag BLF startup service was not configured.\n";
            echo "Add this line inside <settings> and restart FreeSWITCH: " . self::FLAG_BLF_STARTUP_LINE . "\n";
            return;
        }

        $backup = $destination . '.fspbx-update-' . self::VERSION . '.bak';
        if (! File::exists($backup)) {
            File::copy($destination, $backup);
        }

        if (@file_put_contents($destination, $updated) === false) {
            echo "WARNING: Unable to write {$destination}; the flag BLF startup service was not configured.\n";
            return;
        }
        @chmod($destination, 0644);

        echo "Added the flag BLF startup service to {$destination}.\n";
        echo "IMPORTANT: Flag BLF keys light up after a FreeSWITCH restart. Restart it during a quiet period with: systemctl restart freeswitch\n";
    }

    private function autoloadConfigDirectory(): ?string
    {
        $configuredDirectory = null;

        try {
            $configuredDirectory = DefaultSettings::query()
                ->where('default_setting_category', 'switch')
                ->where('default_setting_subcategory', 'conf')
                ->where('default_setting_name', 'dir')
                ->where('default_setting_enabled', 'true')
                ->value('default_setting_value');
        } catch (Throwable $exception) {
            echo "WARNING: Unable to read the configured FreeSWITCH directory: {$exception->getMessage()}\n";
        }

        $candidates = array_values(array_unique(array_filter([
            filled($configuredDirectory) ? rtrim((string) $configuredDirectory, '/') : null,
            '/etc/freeswitch',
        ])));

        foreach ($candidates as $directory) {
            if (File::isDirectory($directory)) {
                return $directory . '/autoload_configs';
            }
        }

        return null;
    }

    private function validXmlContents(string $contents, string $expectedName): bool
    {
        if (trim($contents) === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contents);

            return $xml !== false
                && $xml->getName() === 'configuration'
                && (string) $xml['name'] === $expectedName;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
