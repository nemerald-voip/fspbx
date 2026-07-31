<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkMenuItemRequest;
use App\Http\Requests\BulkUpdateMenuItemGroupsRequest;
use App\Http\Requests\SaveMenuItemRequest;
use App\Http\Requests\SaveMenuRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Auth\UserSessionInvalidationService;
use App\Services\MenuManagerService;
use App\Support\Localization\LocaleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MenuManagerController extends Controller
{
    public function __construct(
        private readonly MenuManagerService $menus,
        private readonly UserSessionInvalidationService $sessions
    ) {
    }

    public function index(): Response|RedirectResponse
    {
        if (! userCheckPermission('menu_view')) {
            return redirect('/');
        }

        return Inertia::render('MenuManager', [
            'managed_menus' => $this->menus->menus(),
            'active_menu_uuid' => session('user.menu_uuid'),
            'routes' => [
                'current_page' => route('menus.index'),
                'data' => route('menus.data', ['menu' => '__MENU__']),
                'store' => route('menus.store'),
                'update' => route('menus.update', ['menu' => '__MENU__']),
                'destroy' => route('menus.destroy', ['menu' => '__MENU__']),
                'items_store' => route('menus.items.store', ['menu' => '__MENU__']),
                'items_update' => route('menus.items.update', ['menu' => '__MENU__', 'menuItem' => '__ITEM__']),
                'items_destroy' => route('menus.items.destroy', ['menu' => '__MENU__', 'menuItem' => '__ITEM__']),
                'items_bulk_destroy' => route('menus.items.bulk-destroy', ['menu' => '__MENU__']),
                'items_bulk_groups' => route('menus.items.bulk-groups', ['menu' => '__MENU__']),
            ],
            'language_options' => $this->languageOptions(),
            'permissions' => [
                'menu_create' => userCheckPermission('menu_add'),
                'menu_update' => userCheckPermission('menu_edit'),
                'menu_destroy' => userCheckPermission('menu_delete'),
                'item_create' => userCheckPermission('menu_item_add'),
                'item_update' => userCheckPermission('menu_item_edit'),
                'item_destroy' => userCheckPermission('menu_item_delete'),
                'group_add' => userCheckPermission('menu_item_group_add'),
                'group_delete' => userCheckPermission('menu_item_group_delete'),
                'group_manage' => userCheckPermission('menu_item_group_add')
                    && userCheckPermission('menu_item_group_delete'),
            ],
        ]);
    }

    public function data(Menu $menu): JsonResponse
    {
        $this->authorizeView();

        return response()->json($this->menus->payload($menu));
    }

    public function store(SaveMenuRequest $request): JsonResponse
    {
        $this->authorizeAction('menu_add');

        $menu = $this->menus->createMenu($request->validated());

        return response()->json([
            'menu' => $this->menus->payload($menu)['menu'],
            'menus' => $this->menus->menus(),
            'messages' => ['success' => [__('Menu created.')]],
        ], 201);
    }

    public function update(SaveMenuRequest $request, Menu $menu): JsonResponse
    {
        $this->authorizeAction('menu_edit');

        $this->menus->updateMenu($menu, $request->validated());
        $this->refreshCurrentNavigation();

        return response()->json([
            'menu' => $this->menus->payload($menu)['menu'],
            'menus' => $this->menus->menus(),
            'messages' => ['success' => [__('Menu updated.')]],
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->authorizeAction('menu_delete');

        $this->menus->deleteMenu($menu);

        return response()->json([
            'menus' => $this->menus->menus(),
            'messages' => ['success' => [__('Menu deleted.')]],
        ]);
    }

    public function storeItem(SaveMenuItemRequest $request, Menu $menu): JsonResponse
    {
        $this->authorizeAction('menu_item_add');

        $item = $this->menus->saveItem(
            $menu,
            $request->validated(),
            manageGroups: $this->canManageGroups()
        );
        $this->refreshCurrentNavigation();

        return response()->json([
            'item' => ['menu_item_uuid' => $item->menu_item_uuid],
            'messages' => ['success' => [__('Menu item created.')]],
        ], 201);
    }

    public function updateItem(
        SaveMenuItemRequest $request,
        Menu $menu,
        MenuItem $menuItem
    ): JsonResponse {
        $this->authorizeAction('menu_item_edit');
        $this->ensureItemBelongsToMenu($menu, $menuItem);

        $this->menus->saveItem(
            $menu,
            $request->validated(),
            $menuItem,
            $this->canManageGroups()
        );
        $this->refreshCurrentNavigation();

        return response()->json([
            'messages' => ['success' => [__('Menu item updated.')]],
        ]);
    }

    public function destroyItem(Menu $menu, MenuItem $menuItem): JsonResponse
    {
        $this->authorizeAction('menu_item_delete');
        $this->ensureItemBelongsToMenu($menu, $menuItem);

        $count = $this->menus->deleteItems($menu, [$menuItem->menu_item_uuid]);
        $this->refreshCurrentNavigation();

        return response()->json([
            'messages' => ['success' => [
                trans_choice(
                    '{1} Menu item deleted.|[2,*] :count menu items deleted.',
                    $count,
                    ['count' => $count]
                ),
            ]],
        ]);
    }

    public function bulkDestroyItems(BulkMenuItemRequest $request, Menu $menu): JsonResponse
    {
        $this->authorizeAction('menu_item_delete');

        $count = $this->menus->deleteItems($menu, $request->validated('items'));
        $this->refreshCurrentNavigation();

        return response()->json([
            'messages' => ['success' => [
                trans_choice(
                    '{1} Menu item deleted.|[2,*] :count menu items deleted.',
                    $count,
                    ['count' => $count]
                ),
            ]],
        ]);
    }

    public function bulkUpdateItemGroups(
        BulkUpdateMenuItemGroupsRequest $request,
        Menu $menu
    ): JsonResponse {
        $data = $request->validated();
        $this->authorizeGroupOperation($data['operation']);

        $count = $this->menus->updateItemGroups(
            $menu,
            $data['items'],
            $data['operation'],
            $data['group_uuids']
        );
        $this->refreshCurrentNavigation();

        return response()->json([
            'messages' => ['success' => [
                trans_choice(
                    '{1} Group membership updated for :count menu item.|[2,*] Group membership updated for :count menu items.',
                    $count,
                    ['count' => $count]
                ),
            ]],
        ]);
    }

    private function authorizeView(): void
    {
        if (! userCheckPermission('menu_view')) {
            abort(403, __('Access denied.'));
        }
    }

    private function authorizeAction(string $permission): void
    {
        if (! userCheckPermission($permission)) {
            abort(403, __('Access denied.'));
        }
    }

    private function ensureItemBelongsToMenu(Menu $menu, MenuItem $item): void
    {
        if ((string) $item->menu_uuid !== (string) $menu->menu_uuid) {
            abort(404);
        }
    }

    private function canManageGroups(): bool
    {
        return userCheckPermission('menu_item_group_add')
            && userCheckPermission('menu_item_group_delete');
    }

    private function authorizeGroupOperation(string $operation): void
    {
        if ($operation === 'add') {
            $this->authorizeAction('menu_item_group_add');

            return;
        }

        if ($operation === 'remove') {
            $this->authorizeAction('menu_item_group_delete');

            return;
        }

        $this->authorizeAction('menu_item_group_add');
        $this->authorizeAction('menu_item_group_delete');
    }

    private function refreshCurrentNavigation(): void
    {
        if (Auth::check()) {
            $this->sessions->refreshCurrentUserMenuSession();
        }
    }

    private function languageOptions(): Collection
    {
        $menuLanguages = Menu::query()
            ->pluck('menu_language')
            ->filter()
            ->map(fn ($locale) => strtolower((string) $locale))
            ->unique();

        return collect(app(LocaleRegistry::class)->available())
            ->filter(fn (array $locale) => $locale['ready'] || $menuLanguages->contains($locale['code']))
            ->map(fn (array $locale) => [
                'value' => $locale['code'],
                'label' => "{$locale['native']} ({$locale['code']})",
            ])
            ->values();
    }
}
