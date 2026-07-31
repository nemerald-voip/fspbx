<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuManagerService
{
    public function menus(): Collection
    {
        return Menu::query()
            ->withCount('items')
            ->orderBy('menu_name')
            ->get()
            ->map(fn (Menu $menu) => [
                'menu_uuid' => $menu->menu_uuid,
                'menu_name' => $menu->menu_name,
                'menu_language' => $menu->menu_language,
                'menu_description' => $menu->menu_description,
                'items_count' => $menu->items_count,
                'is_active' => (string) session('user.menu_uuid') === (string) $menu->menu_uuid,
            ]);
    }

    public function payload(Menu $menu): array
    {
        $items = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->with('groups')
            ->orderByRaw('menu_item_order is null')
            ->orderBy('menu_item_order')
            ->orderBy('menu_item_title')
            ->get();

        $groupLabels = DB::table('v_groups as groups')
            ->leftJoin('v_domains as domains', 'domains.domain_uuid', '=', 'groups.domain_uuid')
            ->whereIn(
                'groups.group_uuid',
                $items->flatMap(fn (MenuItem $item) => $item->groups->pluck('group_uuid'))->filter()->unique()
            )
            ->get([
                'groups.group_uuid',
                'groups.group_name',
                'groups.domain_uuid',
                'domains.domain_name',
                'domains.domain_description',
            ])
            ->keyBy('group_uuid');

        return [
            'menu' => [
                'menu_uuid' => $menu->menu_uuid,
                'menu_name' => $menu->menu_name,
                'menu_language' => $menu->menu_language,
                'menu_description' => $menu->menu_description,
                'is_active' => (string) session('user.menu_uuid') === (string) $menu->menu_uuid,
            ],
            'items' => $items->map(function (MenuItem $item) use ($groupLabels) {
                $groups = $item->groups
                    ->map(function (MenuItemGroup $assignment) use ($groupLabels) {
                        $group = $groupLabels->get($assignment->group_uuid);
                        $account = $group?->domain_description ?: $group?->domain_name;

                        return [
                            'group_uuid' => $assignment->group_uuid,
                            'group_name' => $assignment->group_name ?: $group?->group_name,
                            'label' => ($assignment->group_name ?: $group?->group_name)
                                .($account ? "@{$account}" : ''),
                        ];
                    })
                    ->values();

                return [
                    'menu_item_uuid' => $item->menu_item_uuid,
                    'menu_item_parent_uuid' => $item->menu_item_parent_uuid,
                    'menu_item_title' => $item->menu_item_title,
                    'menu_item_link' => $item->menu_item_link,
                    'menu_item_icon' => $item->menu_item_icon,
                    'menu_item_order' => $item->menu_item_order,
                    'menu_item_description' => $item->menu_item_description,
                    'groups' => $groups,
                    'group_uuids' => $groups->pluck('group_uuid')->filter()->values(),
                ];
            })->values(),
            'group_options' => $this->groupOptions(),
        ];
    }

    public function createMenu(array $data): Menu
    {
        return Menu::query()->create([
            'menu_name' => trim($data['menu_name']),
            'menu_language' => strtolower(trim($data['menu_language'])),
            'menu_description' => $data['menu_description'] ?: null,
        ]);
    }

    public function updateMenu(Menu $menu, array $data): Menu
    {
        $menu->forceFill([
            'menu_name' => trim($data['menu_name']),
            'menu_language' => strtolower(trim($data['menu_language'])),
            'menu_description' => $data['menu_description'] ?: null,
        ])->save();

        return $menu->refresh();
    }

    public function deleteMenu(Menu $menu): void
    {
        $references = collect([
            DB::table('v_default_settings')
                ->where('default_setting_category', 'domain')
                ->where('default_setting_subcategory', 'menu')
                ->where('default_setting_value', $menu->menu_uuid)
                ->count(),
            DB::table('v_domain_settings')
                ->where('domain_setting_category', 'domain')
                ->where('domain_setting_subcategory', 'menu')
                ->where('domain_setting_value', $menu->menu_uuid)
                ->count(),
            DB::table('v_user_settings')
                ->where('user_setting_subcategory', 'menu')
                ->where('user_setting_value', $menu->menu_uuid)
                ->count(),
        ])->sum();

        if ($references > 0) {
            throw ValidationException::withMessages([
                'menu' => __('This menu is assigned to one or more accounts or users and cannot be deleted.'),
            ]);
        }

        DB::transaction(function () use ($menu) {
            MenuLanguage::query()->where('menu_uuid', $menu->menu_uuid)->delete();
            MenuItemGroup::query()->where('menu_uuid', $menu->menu_uuid)->delete();
            MenuItem::query()->where('menu_uuid', $menu->menu_uuid)->delete();
            $menu->delete();
        });
    }

    public function saveItem(
        Menu $menu,
        array $data,
        ?MenuItem $item = null,
        bool $manageGroups = false
    ): MenuItem {
        $parent = null;

        if ($data['menu_item_parent_uuid']) {
            $parent = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where('menu_item_uuid', $data['menu_item_parent_uuid'])
                ->whereNull('menu_item_parent_uuid')
                ->first();

            if (! $parent || ($item && $parent->is($item))) {
                throw ValidationException::withMessages([
                    'menu_item_parent_uuid' => __('Select a valid top-level parent item.'),
                ]);
            }

            if ($item && $item->children()->exists()) {
                throw ValidationException::withMessages([
                    'menu_item_parent_uuid' => __('An item with children must remain at the top level.'),
                ]);
            }
        }

        if ($manageGroups) {
            $accessibleGroupUuids = $this->accessibleGroups()->pluck('group_uuid')->map(fn ($uuid) => (string) $uuid);
            $invalidGroups = collect($data['group_uuids'])->diff($accessibleGroupUuids);

            if ($invalidGroups->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'group_uuids' => __('One or more selected groups are not available.'),
                ]);
            }
        }

        return DB::transaction(function () use ($menu, $data, $item, $manageGroups) {
            $item ??= new MenuItem([
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_add_user' => (string) session('username'),
                'menu_item_add_date' => now()->toDateTimeString(),
            ]);

            $order = $data['menu_item_order'];
            if (! $data['menu_item_parent_uuid'] && $order === null && ! $item->exists) {
                $order = ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->whereNull('menu_item_parent_uuid')
                    ->max('menu_item_order')) + 1;
            }

            $item->forceFill([
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_title' => trim($data['menu_item_title']),
                'menu_item_link' => $data['menu_item_link'] ?: null,
                'menu_item_icon' => $data['menu_item_icon'] ?: null,
                'menu_item_parent_uuid' => $data['menu_item_parent_uuid'],
                'menu_item_order' => $data['menu_item_parent_uuid'] ? null : $order,
                'menu_item_description' => $data['menu_item_description'] ?: null,
                'menu_item_mod_user' => (string) session('username'),
                'menu_item_mod_date' => now()->toDateTimeString(),
            ])->save();

            $language = MenuLanguage::query()->firstOrNew([
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $item->menu_item_uuid,
                'menu_language' => $menu->menu_language,
            ]);
            $language->menu_item_title = $item->menu_item_title;
            $language->save();

            if ($manageGroups) {
                $this->syncAccessibleGroups($menu, $item, collect($data['group_uuids']));
            }

            return $item->refresh();
        });
    }

    public function deleteItems(Menu $menu, array $itemUuids): int
    {
        $selected = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_uuid', $itemUuids)
            ->pluck('menu_item_uuid');

        $all = $selected->values();
        $frontier = $selected;

        while ($frontier->isNotEmpty()) {
            $frontier = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_parent_uuid', $frontier)
                ->whereNotIn('menu_item_uuid', $all)
                ->pluck('menu_item_uuid');
            $all = $all->merge($frontier)->unique()->values();
        }

        DB::transaction(function () use ($menu, $all) {
            MenuLanguage::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_uuid', $all)
                ->delete();
            MenuItemGroup::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_uuid', $all)
                ->delete();
            MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_uuid', $all)
                ->delete();
        });

        return $all->count();
    }

    public function updateItemGroups(
        Menu $menu,
        array $itemUuids,
        string $operation,
        array $groupUuids
    ): int {
        $requestedItemUuids = collect($itemUuids)
            ->map(fn ($uuid) => (string) $uuid)
            ->unique()
            ->values();
        $selectedItemUuids = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_uuid', $requestedItemUuids)
            ->pluck('menu_item_uuid')
            ->map(fn ($uuid) => (string) $uuid)
            ->values();

        if ($selectedItemUuids->count() !== $requestedItemUuids->count()) {
            throw ValidationException::withMessages([
                'items' => __('One or more selected menu items are not available.'),
            ]);
        }

        $groups = $this->accessibleGroups()->keyBy(fn ($group) => (string) $group->group_uuid);
        $selectedGroupUuids = collect($groupUuids)
            ->map(fn ($uuid) => (string) $uuid)
            ->unique()
            ->values();
        $invalidGroups = $selectedGroupUuids->diff($groups->keys());

        if ($invalidGroups->isNotEmpty()) {
            throw ValidationException::withMessages([
                'group_uuids' => __('One or more selected groups are not available.'),
            ]);
        }

        DB::transaction(function () use (
            $menu,
            $selectedItemUuids,
            $operation,
            $selectedGroupUuids,
            $groups
        ) {
            if ($operation === 'remove') {
                MenuItemGroup::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->whereIn('menu_item_uuid', $selectedItemUuids)
                    ->whereIn('group_uuid', $selectedGroupUuids)
                    ->delete();

                return;
            }

            if ($operation === 'replace') {
                MenuItemGroup::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->whereIn('menu_item_uuid', $selectedItemUuids)
                    ->whereIn('group_uuid', $groups->keys())
                    ->delete();
            }

            if ($selectedGroupUuids->isEmpty()) {
                return;
            }

            $existing = MenuItemGroup::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_uuid', $selectedItemUuids)
                ->whereIn('group_uuid', $selectedGroupUuids)
                ->get(['menu_item_uuid', 'group_uuid'])
                ->mapWithKeys(fn (MenuItemGroup $assignment) => [
                    "{$assignment->menu_item_uuid}|{$assignment->group_uuid}" => true,
                ]);

            $rows = [];
            foreach ($selectedItemUuids as $itemUuid) {
                foreach ($selectedGroupUuids as $groupUuid) {
                    if ($existing->has("{$itemUuid}|{$groupUuid}")) {
                        continue;
                    }

                    $group = $groups->get($groupUuid);
                    $rows[] = [
                        'menu_item_group_uuid' => (string) Str::uuid(),
                        'menu_uuid' => $menu->menu_uuid,
                        'menu_item_uuid' => $itemUuid,
                        'group_name' => $group->group_name,
                        'group_uuid' => $group->group_uuid,
                    ];
                }
            }

            if ($rows !== []) {
                MenuItemGroup::query()->insert($rows);
            }
        });

        return $selectedItemUuids->count();
    }

    private function groupOptions(): Collection
    {
        return $this->accessibleGroups()
            ->map(function ($group) {
                $account = $group->domain_description ?: $group->domain_name;

                return [
                    'value' => $group->group_uuid,
                    'label' => $group->group_name.($account ? "@{$account}" : ''),
                ];
            })
            ->values();
    }

    private function accessibleGroups(): Collection
    {
        return DB::table('v_groups as groups')
            ->leftJoin('v_domains as domains', 'domains.domain_uuid', '=', 'groups.domain_uuid')
            ->where(function ($query) {
                $query->whereNull('groups.domain_uuid')
                    ->orWhere('groups.domain_uuid', session('domain_uuid'));
            })
            ->where('groups.group_level', '<=', (int) session('user.group_level', 0))
            ->orderByRaw('groups.domain_uuid is null')
            ->orderBy('groups.group_name')
            ->get([
                'groups.group_uuid',
                'groups.group_name',
                'groups.domain_uuid',
                'groups.group_level',
                'domains.domain_name',
                'domains.domain_description',
            ]);
    }

    private function syncAccessibleGroups(Menu $menu, MenuItem $item, Collection $selectedUuids): void
    {
        $groups = $this->accessibleGroups()->keyBy('group_uuid');
        $accessibleUuids = $groups->keys();

        MenuItemGroup::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $item->menu_item_uuid)
            ->whereIn('group_uuid', $accessibleUuids)
            ->delete();

        $rows = $selectedUuids
            ->unique()
            ->map(function ($groupUuid) use ($menu, $item, $groups) {
                $group = $groups->get($groupUuid);

                return [
                    'menu_item_group_uuid' => (string) Str::uuid(),
                    'menu_uuid' => $menu->menu_uuid,
                    'menu_item_uuid' => $item->menu_item_uuid,
                    'group_name' => $group->group_name,
                    'group_uuid' => $group->group_uuid,
                ];
            })
            ->values()
            ->all();

        if ($rows !== []) {
            MenuItemGroup::query()->insert($rows);
        }
    }
}
