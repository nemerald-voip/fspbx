<?php

namespace Tests\Unit;

use App\Http\Controllers\MenuManagerController;
use App\Http\Requests\CopyMenuRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Services\Install\InstallSchema;
use App\Services\MenuManagerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class MenuManagerCreationTest extends TestCase
{
    private MenuManagerService $menus;
    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'menu_creation_test',
            'database.connections.menu_creation_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        DB::purge('menu_creation_test');
        (new ReflectionMethod(InstallSchema::class, 'ensureMenuSchema'))->invoke(new InstallSchema);
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->text('domain_name')->nullable();
            $table->text('domain_description')->nullable();
        });
        Schema::create('v_groups', function (Blueprint $table) {
            $table->uuid('group_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->text('group_name');
            $table->integer('group_level');
        });
        foreach (['superadmin' => 100, 'admin' => 80, 'user' => 50, 'fax' => 40, 'agent' => 30] as $name => $level) {
            $this->groups[$name] = (string) Str::uuid();
            DB::table('v_groups')->insert([
                'group_uuid' => $this->groups[$name], 'group_name' => $name, 'group_level' => $level,
            ]);
        }
        session()->start();
        session()->put([
            'domain_uuid' => (string) Str::uuid(), 'username' => 'menu-admin',
            'user.group_level' => 100, 'user.menu_uuid' => 'existing-assignment',
            'menu' => ['existing-navigation'],
        ]);
        $this->permissions(['menu_view', 'menu_add', 'menu_item_add', 'menu_item_group_add']);
        $this->menus = app(MenuManagerService::class);
        // Exercise the real form requests/controllers without production auth middleware or data.
        Route::post('/test/menu-create', [MenuManagerController::class, 'store']);
        Route::post('/test/menu-copy/{uuid}', function (CopyMenuRequest $request, string $uuid) {
            return app(MenuManagerController::class)->copy($request, Menu::findOrFail($uuid));
        });
        app()->setLocale('en-us');
    }

    protected function tearDown(): void
    {
        DB::disconnect('menu_creation_test');
        parent::tearDown();
    }

    public static function languages(): array
    {
        return [
            ['en-us', 'Accounts', 'Devices'], ['ru', 'Аккаунты', 'Устройства'],
            ['fr', 'Comptes', 'Appareils'], ['es-419', 'Cuentas', 'Dispositivos'],
            ['pt-br', 'Contas', 'Dispositivos'], ['uk', 'Accounts', 'Devices'],
        ];
    }

    /** @dataProvider languages */
    public function test_creation_uses_selected_language_and_preserves_navigation(string $locale, string $parentTitle, string $childTitle): void
    {
        app()->setLocale($locale === 'ru' ? 'fr' : 'ru');
        $response = $this->postJson('/test/menu-create', [
            'menu_name' => 'New menu', 'menu_language' => $locale,
        ])->assertCreated();
        $menu = Menu::findOrFail($response->json('menu.menu_uuid'));
        $this->assertSame($locale, $menu->menu_language);
        $parent = $menu->items()->where('menu_item_title', $parentTitle)->firstOrFail();
        $child = $menu->items()->where('menu_item_link', '/devices')->firstOrFail();
        $this->assertSame($childTitle, $child->menu_item_title);
        $this->assertSame($parent->menu_item_uuid, $child->menu_item_parent_uuid);
        $this->assertSame(5, $menu->items()->whereNull('menu_item_parent_uuid')->count());
        $this->assertGreaterThan(50, $menu->items()->count());
        $this->assertEqualsCanonicalizing([$this->groups['superadmin'], $this->groups['admin']], $child->groups()->pluck('group_uuid')->all());
        $this->assertSame($menu->items()->count(), MenuLanguage::where('menu_uuid', $menu->menu_uuid)->where('menu_language', $locale)->count());
        $this->assertSame('existing-assignment', session('user.menu_uuid'));
        $this->assertSame(['existing-navigation'], session('menu'));
    }

    public function test_copy_keeps_language_custom_labels_metadata_hierarchy_and_groups_with_new_ids(): void
    {
        $source = $this->menus->createMenu(['menu_name' => 'Source', 'menu_language' => 'ru']);
        $parent = $source->items()->whereNull('menu_item_parent_uuid')->firstOrFail();
        $child = $source->items()->where('menu_item_parent_uuid', $parent->menu_item_uuid)->firstOrFail();
        $child->update([
            'menu_item_title' => 'Мои телефоны', 'menu_item_icon' => 'fa-phone',
            'menu_item_order' => 17, 'menu_item_link' => '/special-page?mode=custom',
            'menu_item_description' => 'Account-specific label', 'menu_item_protected' => 'true',
            'menu_item_add_user' => 'original-creator',
        ]);
        // Parent order in the database must not determine whether remapping works.
        $parent->update(['menu_item_order' => 999]);
        app()->setLocale('fr');
        $response = $this->postJson('/test/menu-copy/'.$source->menu_uuid, [
            'menu_name' => 'Customer menu', 'menu_description' => 'For one account',
            'menu_language' => 'fr', // A crafted request cannot change the copy language.
        ])->assertCreated();
        $copy = Menu::findOrFail($response->json('menu.menu_uuid'));
        $this->assertSame('ru', $copy->menu_language);
        $this->assertSame('For one account', $copy->menu_description);
        $this->assertSame($source->items()->count(), $copy->items()->count());
        $copyChild = $copy->items()->where('menu_item_title', 'Мои телефоны')->firstOrFail();
        foreach (['menu_item_title', 'menu_item_icon', 'menu_item_link', 'menu_item_description', 'menu_item_order', 'menu_item_protected'] as $field) {
            $this->assertSame($child->$field, $copyChild->$field, $field);
        }
        $copyParent = $copy->items()->findOrFail($copyChild->menu_item_parent_uuid);
        $this->assertSame($parent->menu_item_title, $copyParent->menu_item_title);
        $this->assertNotSame($parent->menu_item_uuid, $copyParent->menu_item_uuid);
        $this->assertSame('menu-admin', $copyChild->menu_item_add_user);
        $this->assertSame('original-creator', $child->fresh()->menu_item_add_user);
        $this->assertSame(0, $source->items()->pluck('menu_item_uuid')->intersect($copy->items()->pluck('menu_item_uuid'))->count());
        $this->assertEqualsCanonicalizing($child->groups()->pluck('group_uuid')->all(), $copyChild->groups()->pluck('group_uuid')->all());
        $this->assertNotSame($child->groups()->first()->menu_item_group_uuid, $copyChild->groups()->first()->menu_item_group_uuid);
        $this->assertDatabaseHas('v_menu_languages', [
            'menu_uuid' => $copy->menu_uuid, 'menu_item_uuid' => $copyChild->menu_item_uuid,
            'menu_language' => 'ru', 'menu_item_title' => 'Мои телефоны',
        ]);
        $copyChild->update(['menu_item_title' => 'Changed copy']);
        $this->assertSame('Мои телефоны', $child->fresh()->menu_item_title);
        $this->assertSame('existing-assignment', session('user.menu_uuid'));
    }

    public function test_new_menu_uses_shipped_defaults_even_when_existing_fspbx_is_customized(): void
    {
        $source = $this->menus->createMenu(['menu_name' => 'fspbx', 'menu_language' => 'en-us']);
        $source->items()->where('menu_item_link', '/devices')->update(['menu_item_title' => 'Custom devices']);
        $new = $this->menus->createMenu(['menu_name' => 'New', 'menu_language' => 'en-us']);
        $this->assertSame('Devices', $new->items()->where('menu_item_link', '/devices')->first()->menu_item_title);
        $this->assertSame('Custom devices', $source->items()->where('menu_item_link', '/devices')->first()->menu_item_title);
    }

    public function test_invalid_creation_and_copy_fields_do_not_write_anything(): void
    {
        $this->postJson('/test/menu-create', [])->assertUnprocessable()->assertJsonValidationErrors(['menu_name', 'menu_language']);
        $this->postJson('/test/menu-create', ['menu_name' => 'New', 'menu_language' => 'unknown'])->assertUnprocessable();
        $this->assertSame(0, Menu::count());
        $source = Menu::create(['menu_name' => 'Empty', 'menu_language' => 'en-us']);
        $this->postJson('/test/menu-copy/'.$source->menu_uuid, ['menu_name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('menu_name');
        $this->assertSame(1, Menu::count());
        $this->postJson('/test/menu-copy/'.Str::uuid(), ['menu_name' => 'Copy'])->assertNotFound();
    }

    public static function requiredPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['menu_view', 'menu_add', 'menu_item_add', 'menu_item_group_add']);
    }

    /** @dataProvider requiredPermissions */
    public function test_both_actions_enforce_creation_permissions(string $missing): void
    {
        $source = Menu::create(['menu_name' => 'Empty', 'menu_language' => 'en-us']);
        $this->permissions(array_diff(['menu_view', 'menu_add', 'menu_item_add', 'menu_item_group_add'], [$missing]));
        $this->postJson('/test/menu-create', ['menu_name' => 'New', 'menu_language' => 'en-us'])->assertForbidden();
        $this->postJson('/test/menu-copy/'.$source->menu_uuid, ['menu_name' => 'Copy'])->assertForbidden();
        $this->assertSame(1, Menu::count());
    }

    public function test_copy_rejects_inaccessible_group_assignments_without_a_partial_menu(): void
    {
        $source = $this->menus->createMenu(['menu_name' => 'Source', 'menu_language' => 'en-us']);
        session()->put('user.group_level', 80);
        $this->postJson('/test/menu-copy/'.$source->menu_uuid, ['menu_name' => 'Copy'])->assertUnprocessable()->assertJsonValidationErrors('menu_name');
        $this->assertSame(1, Menu::count());
        session()->put('user.group_level', 100);
        DB::table('v_groups')->where('group_uuid', $this->groups['admin'])->update(['domain_uuid' => (string) Str::uuid()]);
        $this->postJson('/test/menu-copy/'.$source->menu_uuid, ['menu_name' => 'Copy'])->assertUnprocessable();
        $this->assertSame(1, Menu::count());
    }

    public function test_new_menus_never_assign_groups_outside_the_admins_scope(): void
    {
        session()->put('user.group_level', 80);
        $menu = $this->menus->createMenu(['menu_name' => 'New', 'menu_language' => 'en-us']);
        $this->assertSame(0, MenuItemGroup::where('menu_uuid', $menu->menu_uuid)->where('group_uuid', $this->groups['superadmin'])->count());
        $this->assertGreaterThan(0, MenuItemGroup::where('menu_uuid', $menu->menu_uuid)->where('group_uuid', $this->groups['admin'])->count());
    }

    public function test_creation_and_copy_roll_back_if_an_item_write_fails(): void
    {
        $source = $this->menus->createMenu(['menu_name' => 'Source', 'menu_language' => 'ru']);
        $counts = [MenuItem::count(), MenuItemGroup::count(), MenuLanguage::count()];
        DB::unprepared("CREATE TRIGGER reject_menu_item BEFORE INSERT ON v_menu_items BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        foreach (['create', 'copy'] as $action) {
            try {
                $action === 'create'
                    ? $this->menus->createMenu(['menu_name' => 'New', 'menu_language' => 'fr'])
                    : $this->menus->copyMenu($source, ['menu_name' => 'Copy']);
                $this->fail('Expected the item write to fail.');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertStringContainsString('fixture failure', $exception->getMessage());
            }
            $this->assertSame(1, Menu::count());
            $this->assertSame($counts, [MenuItem::count(), MenuItemGroup::count(), MenuLanguage::count()]);
        }
    }

    public function test_language_picker_includes_languages_with_incomplete_catalogs(): void
    {
        $options = (new ReflectionMethod(MenuManagerController::class, 'languageOptions'))->invoke(app(MenuManagerController::class));
        $this->assertContains('uk', $options->pluck('value')->all());
        $this->assertCount(count(config('locales.locales')), $options);
    }

    private function permissions(array $permissions): void
    {
        session()->put('permissions', array_map(fn ($name) => (object) ['permission_name' => $name], array_values($permissions)));
    }
}
