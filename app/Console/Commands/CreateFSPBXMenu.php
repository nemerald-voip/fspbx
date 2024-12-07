<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Menu;
use App\Models\MenuItem;

class CreateFSPBXMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:create-fspbx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the FS PBX Recommended Menu with predefined items.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $menuName = 'fspbx';
        $menuDescription = 'FS PBX Recommended Menu';

        // Check if the menu already exists
        $menu = Menu::where('menu_name', $menuName)->first();

        if (!$menu) {
            $this->info("Creating menu: $menuName");

            // Create the menu
            $menu = Menu::create([
                // 'menu_uuid' => Str::uuid(),
                'menu_name' => $menuName,
                'menu_description' => $menuDescription,
            ]);

            $this->info("Menu created with UUID: {$menu->menu_uuid}");
        } else {
            $this->info("Menu '$menuName' already exists with UUID: {$menu->menu_uuid}");
        }

        // Predefined menu items
        $menuItems = [
            [
                'title' => 'Dashboard',
                'link' => '/dashboard',
                'order' => 1,
                'parent_uuid' => null,
            ],
            [
                'title' => 'Settings',
                'link' => '/settings',
                'order' => 2,
                'parent_uuid' => null,
            ],
            [
                'title' => 'Extensions',
                'link' => '/extensions',
                'order' => 3,
                'parent_uuid' => null,
            ],
            [
                'title' => 'Call Logs',
                'link' => '/call-logs',
                'order' => 4,
                'parent_uuid' => null,
            ],
            [
                'title' => 'Advanced',
                'link' => '/advanced',
                'order' => 5,
                'parent_uuid' => null,
            ],
            [
                'title' => 'Sub-Settings',
                'link' => '/settings/sub-settings',
                'order' => 6,
                'parent_uuid' => null,
            ],
        ];

        // Add menu items
        $this->info('Adding menu items...');
        foreach ($menuItems as $item) {
            $existingItem = MenuItem::where('menu_uuid', $menu->menu_uuid)
                ->where('menu_item_title', $item['title'])
                ->first();

            if ($existingItem) {
                $this->warn(" - Skipped: {$item['title']} already exists with UUID: {$existingItem->menu_item_uuid}");
                continue;
            }

            $menuItem = MenuItem::create([
                'menu_item_uuid' => Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_title' => $item['title'],
                'menu_item_link' => $item['link'],
                'menu_item_order' => $item['order'],
                'menu_item_parent_uuid' => $item['parent_uuid'],
            ]);

            $this->info(" - Added: {$menuItem->menu_item_title} with UUID: {$menuItem->menu_item_uuid}");
        }

        $this->info("Menu '$menuName' and items created successfully.");
        return Command::SUCCESS;
    }
}
