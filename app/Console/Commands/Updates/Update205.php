<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update205
{
    public function apply(): bool
    {
        try {
            // Preserve custom models, enabled flags, UUIDs, and account overrides.
            // The normal seeder creates the setting if it is missing.
            $updated = DB::table('v_default_settings')
                ->where('default_setting_category', 'voicemail')
                ->where('default_setting_subcategory', 'openai_transcription_model')
                ->where('default_setting_name', 'text')
                ->whereIn('default_setting_value', [
                    'whisper-1',
                    'gpt-4o-transcribe',
                    'gpt-4o-mini-transcribe',
                    'gpt-4o-mini-transcribe-2025-03-20',
                    'gpt-4o-mini-transcribe-2025-12-15',
                ])
                ->update([
                    'default_setting_value' => 'gpt-transcribe',
                    'default_setting_description' => 'gpt-transcribe - transcription of recorded voicemail audio',
                ]);

            echo "OpenAI voicemail transcription: updated {$updated} default setting(s) to gpt-transcribe.\n";

            DB::transaction(function () {
                // Repeat Update194's menu-only repair without its provisioning migration.
                $this->ensureMenuItem('Phonebooks', '/phonebooks');
                $this->ensureMenuItem('Paging Groups', '/paging-groups');
            });

            return true;
        } catch (Throwable $exception) {
            echo "Update 2.0.5 failed: {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureMenuItem(string $title, string $link): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();

        if (! $menu) {
            echo "{$title} menu: skipped; menu 'fspbx' was not found.\n";
            return;
        }

        $locale = $menu->menu_language ?: 'en-us';
        $item = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', $link)
            ->first();
        $created = false;

        if (! $item) {
            $applications = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereNull('menu_item_parent_uuid')
                ->whereIn('menu_item_title', ['Applications', __('Applications', [], $locale)])
                ->first();

            if (! $applications) {
                echo "{$title} menu: skipped; Applications was not found in menu 'fspbx'.\n";
                return;
            }

            $item = MenuItem::query()->create([
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applications->menu_item_uuid,
                'menu_item_title' => __($title, [], $locale),
                'menu_item_link' => $link,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $applications->menu_item_uuid)
                    ->max('menu_item_order')) + 1,
            ]);
            $created = true;
        }

        // Existing labels, placement, order, and translations remain administrator-owned.
        $language = MenuLanguage::query()->firstOrCreate([
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $item->menu_item_uuid,
            'menu_language' => $locale,
        ], [
            'menu_item_title' => $item->menu_item_title,
        ]);

        $assignmentsAdded = 0;
        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->whereNull('domain_uuid')->where('group_name', $groupName)->first();

            if (! $group) {
                echo "{$title} menu: group '{$groupName}' was not found; menu access skipped.\n";
                continue;
            }

            // These are menu visibility assignments, not application permissions.
            $assignment = MenuItemGroup::query()->firstOrCreate([
                'menu_item_uuid' => $item->menu_item_uuid,
                'group_uuid' => $group->group_uuid,
            ], [
                'menu_uuid' => $menu->menu_uuid,
                'group_name' => $groupName,
            ]);
            $assignmentsAdded += (int) $assignment->wasRecentlyCreated;
        }

        $status = $created ? 'added item' : 'item already exists';
        $languagesAdded = (int) $language->wasRecentlyCreated;
        echo "{$title} menu: {$status}; added {$languagesAdded} language row(s), {$assignmentsAdded} group assignment(s).\n";
    }
}
