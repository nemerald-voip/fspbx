<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Services\DialplanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class Update198
{
    private const VERSION = '1.9.8';
    private const APP_UUID = '7bc57f0c-00a2-4f72-9f41-7ebebc1c318c';
    private const PUBLIC_DIALPLAN_UUID = '95da3fe2-f561-4897-8eaf-98dbde0a1404';
    private const PUBLIC_RETURN_CONDITION_UUID = '44cd9616-ac15-490e-81a7-5aed1396b80e';
    private const PUBLIC_RETURN_ACTION_UUID = '26a4947c-cde5-4526-83cf-22141b06ab07';
    private const AGENT_STATUS_APP_UUID = '2eb032c5-c79d-4096-ac90-8a47fe40f411';
    private const AGENT_BREAK_APP_UUID = '17a937f4-82f1-4a0f-b3a8-213db15127cf';
    private const OLD_AGENT_STATUS_EXPRESSION = '^(?:agent\+|\*22)(.+)$';
    private const NEW_AGENT_STATUS_EXPRESSION = '^agent(\d+)$';
    private const OLD_AGENT_BREAK_EXPRESSION = '^(?:agent\+|\*24)(.+)$';
    private const NEW_AGENT_BREAK_EXPRESSION = '^break(\d+)$';
    private const AGENT_LOGIN_TOGGLE_ACTION = 'lua/agent_toggle.lua login $1';
    private const AGENT_BREAK_TOGGLE_ACTION = 'lua/agent_toggle.lua break $1';
    private const AGENT_BLF_STARTUP_LINE = '<param name="startup-script" value="lua/agent_blf.lua"/>';

    public function apply(): bool
    {
        try {
            $this->ensureMenuItem();
            $this->ensurePublicReturnDialplan();
            $this->updateAgentPresenceDialplans();
            $this->patchAgentBlfStartupConfiguration();
            $this->repairHiredis();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function repairHiredis(): void
    {
        echo "== FS PBX: mod_hiredis repair ==\n";

        try {
            $autoloadDirectory = $this->autoloadConfigDirectory();

            if (! $autoloadDirectory) {
                echo "WARNING: The FreeSWITCH configuration directory was not found; skipping mod_hiredis repair.\n";
                return;
            }

            File::ensureDirectoryExists($autoloadDirectory, 0755, true);

            $configReady = false;

            try {
                $configReady = $this->restoreHiredisConfig($autoloadDirectory);
            } catch (Throwable $exception) {
                echo "WARNING: Unable to restore hiredis.conf.xml: {$exception->getMessage()}\n";
            }

            $this->ensureHiredisModuleRecord();

            try {
                $this->ensureHiredisModuleLoadLine($autoloadDirectory);
            } catch (Throwable $exception) {
                echo "WARNING: Unable to update modules.conf.xml for mod_hiredis: {$exception->getMessage()}\n";
            }

            if ($configReady) {
                $this->loadHiredisModule();
            } else {
                echo "WARNING: mod_hiredis was not loaded because hiredis.conf.xml could not be restored.\n";
            }
        } catch (Throwable $exception) {
            echo "WARNING: mod_hiredis repair encountered an error: {$exception->getMessage()}\n";
        }
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

    private function restoreHiredisConfig(string $autoloadDirectory): bool
    {
        $source = resource_path('autoload_configs/hiredis.conf.xml');
        $destination = $autoloadDirectory . '/hiredis.conf.xml';

        if (! $this->validXmlConfiguration($source, 'hiredis.conf')) {
            echo "WARNING: Canonical hiredis.conf.xml is missing or invalid at {$source}.\n";
            return false;
        }

        if ($this->validXmlConfiguration($destination, 'hiredis.conf')) {
            echo "hiredis.conf.xml already exists and is valid; preserving it.\n";
            return true;
        }

        if (File::exists($destination)) {
            $backup = $destination . '.fspbx-update-' . self::VERSION . '.bak';

            if (! File::exists($backup)) {
                File::copy($destination, $backup);
                echo "Backed up the invalid hiredis.conf.xml to {$backup}.\n";
            }
        }

        File::copy($source, $destination);
        File::chmod($destination, 0644);
        echo "Restored {$destination} from the tracked FS PBX configuration.\n";

        return $this->validXmlConfiguration($destination, 'hiredis.conf');
    }

    private function ensureHiredisModuleRecord(): void
    {
        try {
            $values = [
                'module_label' => 'Hiredis',
                'module_category' => 'Applications',
                'module_order' => 151,
                'module_enabled' => 'true',
                'module_default_enabled' => 'true',
                'module_description' => 'Redis API integration for FreeSWITCH.',
            ];

            $module = DB::table('v_modules')->where('module_name', 'mod_hiredis')->first();

            if ($module) {
                DB::table('v_modules')
                    ->where('module_name', 'mod_hiredis')
                    ->update($values + ['update_date' => now()]);
                echo "Ensured mod_hiredis is enabled in v_modules.\n";
                return;
            }

            DB::table('v_modules')->insert($values + [
                'module_uuid' => (string) Str::uuid(),
                'module_name' => 'mod_hiredis',
                'insert_date' => now(),
            ]);
            echo "Added enabled mod_hiredis row to v_modules.\n";
        } catch (Throwable $exception) {
            echo "WARNING: Unable to ensure mod_hiredis in v_modules: {$exception->getMessage()}\n";
        }
    }

    private function ensureHiredisModuleLoadLine(string $autoloadDirectory): void
    {
        $modulesPath = $autoloadDirectory . '/modules.conf.xml';

        if (! File::exists($modulesPath)) {
            $source = resource_path('autoload_configs/modules.conf.xml');

            if (! $this->validXmlConfiguration($source, 'modules.conf')) {
                echo "WARNING: Canonical modules.conf.xml is missing or invalid; mod_hiredis autoload could not be repaired.\n";
                return;
            }

            File::copy($source, $modulesPath);
            File::chmod($modulesPath, 0644);
            echo "Restored missing {$modulesPath} from the tracked FS PBX configuration.\n";
        }

        $contents = File::get($modulesPath);

        if (preg_match('/^[ \t]*<load\s+module=["\']mod_hiredis["\']\s*\/>[ \t]*$/mi', $contents)) {
            echo "modules.conf.xml already loads mod_hiredis.\n";
            return;
        }

        $loadLine = "\t\t<load module=\"mod_hiredis\"/>\n";
        $updated = preg_replace('/^([ \t]*<\/modules>[ \t]*\R?)/mi', $loadLine . '$1', $contents, 1, $count);

        if ($count === 0 || $updated === null) {
            echo "WARNING: Could not find the modules closing tag in {$modulesPath}; mod_hiredis was not added.\n";
            return;
        }

        File::put($modulesPath, $updated);
        echo "Added mod_hiredis to {$modulesPath}.\n";
    }

    private function loadHiredisModule(): void
    {
        if ($this->moduleExists('mod_hiredis')) {
            echo "mod_hiredis is already loaded.\n";
            return;
        }

        $this->runFreeswitchCommand('reloadxml');
        $this->runFreeswitchCommand('load mod_hiredis');

        echo $this->moduleExists('mod_hiredis')
            ? "mod_hiredis loaded successfully.\n"
            : "WARNING: mod_hiredis is configured to autoload, but it is not loaded right now.\n";
    }

    private function moduleExists(string $module): bool
    {
        [$successful, $output] = $this->runFreeswitchCommand("module_exists {$module}", false);

        return $successful && str_contains(strtolower($output), 'true');
    }

    private function runFreeswitchCommand(string $command, bool $warn = true): array
    {
        try {
            $process = new Process(['fs_cli', '-x', $command]);
            $process->setTimeout(30);
            $process->run();

            $output = trim($process->getOutput() . $process->getErrorOutput());
            $successful = $process->isSuccessful() && ! str_starts_with(ltrim($output), '-ERR');

            if (! $successful && $warn) {
                echo "WARNING: FreeSWITCH command '{$command}' failed" . ($output !== '' ? ": {$output}" : '.') . "\n";
            }

            return [$successful, $output];
        } catch (Throwable $exception) {
            if ($warn) {
                echo "WARNING: Unable to run FreeSWITCH command '{$command}': {$exception->getMessage()}\n";
            }

            return [false, ''];
        }
    }

    private function validXmlConfiguration(string $path, string $expectedName): bool
    {
        if (! File::exists($path) || File::size($path) === 0) {
            return false;
        }

        return $this->validXmlContents(File::get($path), $expectedName);
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

    private function updateAgentPresenceDialplans(bool $reloadXml = true): void
    {
        $changes = DB::transaction(function (): int {
            $changes = 0;
            $changes += $this->updateAgentPresenceDialplan(
                self::AGENT_STATUS_APP_UUID,
                self::OLD_AGENT_STATUS_EXPRESSION,
                self::NEW_AGENT_STATUS_EXPRESSION,
                self::AGENT_LOGIN_TOGGLE_ACTION
            );
            $changes += $this->updateAgentPresenceDialplan(
                self::AGENT_BREAK_APP_UUID,
                self::OLD_AGENT_BREAK_EXPRESSION,
                self::NEW_AGENT_BREAK_EXPRESSION,
                self::AGENT_BREAK_TOGGLE_ACTION
            );

            return $changes;
        });

        if ($changes === 0) {
            echo "Agent login and break dialplans already use compact BLF keys.\n";
            return;
        }

        app(DialplanService::class)->clearDialplanCache('global');
        if ($reloadXml) {
            $this->runFreeswitchCommand('reloadxml');
        }

        echo "Updated {$changes} Agent login/break dialplan record(s) in place.\n";
    }

    private function updateAgentPresenceDialplan(
        string $appUuid,
        string $oldExpression,
        string $newExpression,
        string $luaAction
    ): int {
        $changes = 0;
        $dialplans = Dialplans::query()
            ->where('app_uuid', $appUuid)
            ->get(['dialplan_uuid', 'dialplan_xml']);

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $updatedXml = $this->replaceCompactAgentCondition(
                $xml,
                $oldExpression,
                $newExpression,
                $luaAction
            );

            if ($updatedXml !== $xml) {
                $dialplan->forceFill([
                    'dialplan_xml' => $updatedXml,
                    'update_date' => now(),
                ])->save();
                $changes++;
            }

            $condition = DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_tag', 'condition')
                ->where('dialplan_detail_type', 'destination_number')
                ->whereIn('dialplan_detail_data', [$oldExpression, $newExpression])
                ->orderBy('dialplan_detail_group')
                ->orderBy('dialplan_detail_order')
                ->first();

            if (! $condition) {
                continue;
            }

            $detailChanges = 0;
            if ($condition->dialplan_detail_data !== $newExpression) {
                $condition->forceFill([
                    'dialplan_detail_data' => $newExpression,
                    'update_date' => now(),
                ])->save();
                $detailChanges++;
            }

            $groupQuery = DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_group', $condition->dialplan_detail_group)
                ->where('dialplan_detail_tag', 'action');

            $toggleDetail = (clone $groupQuery)
                ->where('dialplan_detail_type', 'lua')
                ->where('dialplan_detail_data', $luaAction)
                ->first()
                ?? (clone $groupQuery)
                    ->where('dialplan_detail_type', 'set')
                    ->where('dialplan_detail_data', 'agent_id=$1')
                    ->first();

            if ($toggleDetail) {
                if ($toggleDetail->dialplan_detail_type !== 'lua'
                    || $toggleDetail->dialplan_detail_data !== $luaAction
                    || $toggleDetail->getRawOriginal('dialplan_detail_enabled') !== 'true') {
                    $toggleDetail->forceFill([
                        'dialplan_detail_type' => 'lua',
                        'dialplan_detail_data' => $luaAction,
                        'dialplan_detail_enabled' => 'true',
                        'update_date' => now(),
                    ])->save();
                    $detailChanges++;
                }

                // The compact condition now has one purpose-built Lua action.
                // Remove only the obsolete actions from this condition group so
                // the editable details match dialplan_xml and the new template.
                $detailChanges += DialplanDetails::query()
                    ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                    ->where('dialplan_detail_group', $condition->dialplan_detail_group)
                    ->whereNotIn('dialplan_detail_uuid', [
                        $condition->dialplan_detail_uuid,
                        $toggleDetail->dialplan_detail_uuid,
                    ])
                    ->delete();
            }

            if ($detailChanges > 0 && $updatedXml === $xml) {
                $changes++;
            }
        }

        return $changes;
    }

    private function replaceCompactAgentCondition(
        string $xml,
        string $oldExpression,
        string $newExpression,
        string $luaAction
    ): string {
        $pattern = '/<condition\b[^>]*expression="(?:'
            . preg_quote($oldExpression, '/')
            . '|'
            . preg_quote($newExpression, '/')
            . ')"[^>]*>.*?<\/condition>/s';

        return preg_replace_callback($pattern, function () use ($newExpression, $luaAction) {
            return '<condition field="destination_number" expression="' . $newExpression . '">' . "\n"
                . "\t\t" . '<action application="lua" data="' . $luaAction . '" enabled="true"/>' . "\n"
                . "\t" . '</condition>';
        }, $xml, 1) ?? $xml;
    }

    private function patchAgentBlfStartupConfiguration(?string $autoloadDirectory = null): void
    {
        try {
            $autoloadDirectory ??= $this->autoloadConfigDirectory();
            if (! $autoloadDirectory) {
                echo "WARNING: The FreeSWITCH configuration directory was not found; agent BLF startup was not configured.\n";
                return;
            }

            File::ensureDirectoryExists($autoloadDirectory, 0755, true);
            $source = resource_path('autoload_configs/lua.conf.xml');
            $destination = $autoloadDirectory . '/lua.conf.xml';

            if (! $this->validXmlConfiguration($source, 'lua.conf')) {
                echo "WARNING: Canonical lua.conf.xml is missing or invalid; agent BLF startup was not configured.\n";
                return;
            }

            if (! File::exists($destination)) {
                File::copy($source, $destination);
                File::chmod($destination, 0644);
                echo "Restored {$destination} with the agent BLF startup service.\n";
            } else {
                $contents = File::get($destination);
                $linePattern = '/^[ \t]*<param\b(?=[^>]*\bname=["\']startup-script["\'])(?=[^>]*\bvalue=["\']lua\/agent_blf\.lua["\'])[^>]*\/>[ \t]*\R?/mi';
                $matches = preg_match_all($linePattern, $contents);

                if ($matches !== 1) {
                    $withoutDuplicates = preg_replace($linePattern, '', $contents) ?? $contents;
                    $startupBlock = "    <!-- FS PBX: Call Center Agent BLF daemon -->\n"
                        . '    ' . self::AGENT_BLF_STARTUP_LINE . "\n";
                    $updated = preg_replace(
                        '/^([ \t]*<\/settings>[ \t]*\R?)/mi',
                        $startupBlock . '$1',
                        $withoutDuplicates,
                        1,
                        $count
                    );

                    if ($count !== 1 || $updated === null || ! $this->validXmlContents($updated, 'lua.conf')) {
                        echo "WARNING: Unable to add the agent BLF startup service to {$destination}.\n";
                        return;
                    }

                    $backup = $destination . '.fspbx-update-' . self::VERSION . '.bak';
                    if (! File::exists($backup)) {
                        File::copy($destination, $backup);
                    }

                    File::put($destination, $updated);
                    File::chmod($destination, 0644);
                    echo "Added the agent BLF startup service to {$destination}.\n";
                } elseif (! $this->validXmlContents($contents, 'lua.conf')) {
                    echo "WARNING: {$destination} already references agent BLF, but the XML is invalid.\n";
                    return;
                } else {
                    echo "lua.conf.xml already starts the agent BLF service.\n";
                }
            }

            echo "FreeSWITCH must be restarted manually to enable agent BLF presence. Run: sudo systemctl restart freeswitch\n";
        } catch (Throwable $exception) {
            echo "WARNING: Unable to configure agent BLF startup: {$exception->getMessage()}\n";
        }
    }

    private function ensurePublicReturnDialplan(): void
    {
        $dialplan = Dialplans::query()->find(self::PUBLIC_DIALPLAN_UUID) ?? new Dialplans();
        $isNew = ! $dialplan->exists;

        $dialplan->forceFill([
            'domain_uuid' => null,
            'dialplan_uuid' => self::PUBLIC_DIALPLAN_UUID,
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => 'AI Agent Return',
            'dialplan_destination' => 'sip_req_user',
            'dialplan_number' => 'xfer.<agent-uuid>.<extension>',
            'dialplan_context' => 'public',
            'dialplan_continue' => 'false',
            'dialplan_order' => 100,
            'dialplan_enabled' => true,
            'dialplan_description' => 'Validates AI provider transfer targets and returns them to the owning account.',
            'dialplan_xml' => <<<'XML'
<extension name="AI Agent Return" continue="false" uuid="95da3fe2-f561-4897-8eaf-98dbde0a1404">
	<condition field="${sip_req_user}" expression="^xfer\.[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[0-9*#]+$">
		<action application="lua" data="ai_agent_return.lua"/>
	</condition>
</extension>
XML,
            $isNew ? 'insert_date' : 'update_date' => now(),
        ])->save();

        $details = [
            self::PUBLIC_RETURN_CONDITION_UUID => [
                'dialplan_detail_tag' => 'condition',
                'dialplan_detail_type' => '${sip_req_user}',
                'dialplan_detail_data' => '^xfer\.[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[0-9*#]+$',
                'dialplan_detail_order' => 5,
            ],
            self::PUBLIC_RETURN_ACTION_UUID => [
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'lua',
                'dialplan_detail_data' => 'ai_agent_return.lua',
                'dialplan_detail_order' => 10,
            ],
        ];

        foreach ($details as $detailUuid => $values) {
            DialplanDetails::query()->updateOrCreate([
                'dialplan_detail_uuid' => $detailUuid,
            ], $values + [
                'domain_uuid' => null,
                'dialplan_uuid' => self::PUBLIC_DIALPLAN_UUID,
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_enabled' => true,
            ]);
        }

        DialplanDetails::query()
            ->where('dialplan_uuid', self::PUBLIC_DIALPLAN_UUID)
            ->whereNotIn('dialplan_detail_uuid', array_keys($details))
            ->delete();

        app(DialplanService::class)->clearDialplanCache('public');
        echo "Ensured the global AI Agent return dialplan.\n";
    }

    private function ensureMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();
        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping AI Agents menu item.\n";
            return;
        }

        $parent = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();
        if (! $parent) {
            echo "Applications menu item was not found; skipping AI Agents menu item.\n";
            return;
        }

        $item = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where(function ($query) {
                $query->where('menu_item_link', '/ai-agents')->orWhere('menu_item_title', 'AI Agents');
            })->first();

        $values = [
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_parent_uuid' => $parent->menu_item_uuid,
            'menu_item_title' => 'AI Agents',
            'menu_item_link' => '/ai-agents',
            'menu_item_icon' => '',
            'menu_item_category' => 'internal',
            'menu_item_protected' => 'false',
        ];

        if ($item) {
            $item->forceFill($values)->save();
        } else {
            $item = MenuItem::query()->create($values + [
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_item_order' => ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $parent->menu_item_uuid)
                    ->max('menu_item_order')) + 1,
            ]);
        }

        $language = MenuLanguage::query()->firstOrCreate([
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $item->menu_item_uuid,
            'menu_language' => 'en-us',
        ], [
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_item_title' => 'AI Agents',
        ]);
        $language->forceFill(['menu_item_title' => 'AI Agents'])->save();

        $superadmin = Groups::query()->where('group_name', 'superadmin')->first();
        if ($superadmin) {
            MenuItemGroup::query()->firstOrCreate([
                'menu_item_uuid' => $item->menu_item_uuid,
                'group_uuid' => $superadmin->group_uuid,
            ], [
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'group_name' => 'superadmin',
            ]);
        }

        echo "Ensured the superadmin AI Agents menu item.\n";
    }
}
