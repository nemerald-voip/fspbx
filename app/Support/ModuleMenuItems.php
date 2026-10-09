<?php

namespace App\Support;

/**
 * Menu entries that optional modules add at request time instead of storing
 * them in v_menu_items. A module registers its entries from its service
 * provider, which only boots while the module is enabled; apply() then adds
 * the entries the user has permission for to the menu shared with the pages.
 */
class ModuleMenuItems
{
    /** Links that identify the Advanced menu in every menu and language. */
    protected const ADVANCED_LINKS = ['/default-settings', '/menus', '/modules'];

    /** @var array<int, array{title: string, link: string, permission: ?string}> */
    protected array $advanced = [];

    /**
     * Add an entry to the Advanced menu for users with $permission. The title
     * is plain English and goes through the shared translation catalog.
     */
    public function addToAdvanced(string $title, string $link, ?string $permission = null): void
    {
        $this->advanced[] = ['title' => $title, 'link' => $link, 'permission' => $permission];
    }

    /**
     * The session menu with the user's module entries added. Works on copies
     * so the menu stored in the session never changes.
     */
    public function apply($menu)
    {
        $entries = collect($this->advanced)
            ->filter(fn (array $item) => ! $item['permission'] || userCheckPermission($item['permission']))
            ->map(fn (array $item) => (object) [
                'menu_item_uuid' => 'module:'.$item['link'],
                'menu_item_title' => __($item['title']),
                'menu_item_link' => $item['link'],
                'menu_item_icon' => null,
                'menu_item_order' => null,
            ])
            ->values();

        if ($entries->isEmpty() || ! $menu) {
            return $menu;
        }

        $menu = collect($menu)->map(fn ($top) => clone $top);
        $advanced = $menu->first(fn ($top) => collect($top->child_menu ?? [])
            ->contains(fn ($child) => in_array($child->menu_item_link, self::ADVANCED_LINKS, true)));

        if (! $advanced) {
            // No Advanced menu for this user: give the entries their own menu.
            return $menu->push((object) [
                'menu_item_uuid' => 'module:menu',
                'menu_item_title' => $entries->count() === 1 ? $entries->first()->menu_item_title : __('Advanced'),
                'menu_item_link' => null,
                'menu_item_icon' => null,
                'menu_item_order' => null,
                'child_menu' => $entries,
            ]);
        }

        // Skip links an administrator already added to the menu by hand.
        $existing = collect($advanced->child_menu)->pluck('menu_item_link')->all();
        $advanced->child_menu = collect($advanced->child_menu)
            ->concat($entries->reject(fn ($entry) => in_array($entry->menu_item_link, $existing, true)))
            ->sortBy(fn ($child) => mb_strtolower((string) $child->menu_item_title))
            ->values();

        return $menu;
    }
}
