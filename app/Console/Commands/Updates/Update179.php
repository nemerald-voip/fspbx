<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\File;

class Update179
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {
        $this->updateBasicQueueMenuItems();
        $this->updateBasicQueueAgentStatusMenuItems();
        $this->patchLocalStreamXmlGenerator();

        echo "Update 1.7.9 completed successfully.\n";
        return true;
    }

    private function updateBasicQueueMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/call_centers/call_center_queues.php')
            ->update([
                'menu_item_title' => 'Basic Queue',
                'menu_item_link' => '/basic-queues',
            ]);

        echo $updated === 0
            ? "No Basic Queue menu items required updating.\n"
            : "Updated {$updated} Basic Queue menu item(s).\n";
    }

    private function updateBasicQueueAgentStatusMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/call_centers/call_center_agent_status.php')
            ->update([
                'menu_item_title' => 'Agent Status',
                'menu_item_link' => '/basic-queues/agent-status',
            ]);

        echo $updated === 0
            ? "No Agent Status menu items required updating.\n"
            : "Updated {$updated} Agent Status menu item(s).\n";
    }

    private function patchLocalStreamXmlGenerator(): void
    {
        $path = base_path('public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua');

        if (! File::exists($path)) {
            echo "local_stream.conf.lua was not found; skipping Music on Hold XML generator patch.\n";
            return;
        }

        $contents = File::get($path);
        $original = $contents;

        $contents = str_replace(
            'sql = sql .. "order by s.music_on_hold_name asc "',
            'sql = sql .. "order by d.domain_name asc, s.music_on_hold_name asc, s.music_on_hold_rate asc "',
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				--combine the name, domain_name and the rate 
				name = '';
				if (row.domain_uuid ~= nil and string.len(row.domain_uuid) > 0) then
					name = row.domain_name..'/';
				end
				name = name .. row.music_on_hold_name;
				if (row.music_on_hold_rate ~= nil and #row.music_on_hold_rate > 0) then
					name = name .. '/' .. row.music_on_hold_rate;
				end
LUA,
            <<<'LUA'
				--combine the name and domain_name
				name = '';
				if (row.domain_uuid ~= nil and string.len(row.domain_uuid) > 0) then
					name = row.domain_name..'/';
				end
				name = name .. row.music_on_hold_name;
LUA,
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				rate = row.music_on_hold_rate;
				if rate == '' then
					rate = '48000';
				end
LUA,
            <<<'LUA'
				rate = row.music_on_hold_rate;
				if rate == nil or rate == '' then
					rate = '48000';
				end

				--set channels and interval
				channels = row.music_on_hold_channels;
				if channels == nil or channels == '' then
					channels = '1';
				end
				interval = row.music_on_hold_interval;
				if interval == nil or interval == '' then
					interval = '20';
				end
LUA,
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				xml:append([[			<param name="channels" value="1"/>]]);
				xml:append([[			<param name="interval" value="20"/>]]);
LUA,
            <<<'LUA'
				xml:append([[			<param name="channels" value="]] .. xml.sanitize(channels) .. [["/>]]);
				xml:append([[			<param name="interval" value="]] .. xml.sanitize(interval) .. [["/>]]);
LUA,
            $contents
        );

        if ($contents === $original) {
            echo "Music on Hold XML generator already up to date.\n";
            return;
        }

        File::put($path, $contents);
        echo "Patched Music on Hold XML generator.\n";
    }
}
