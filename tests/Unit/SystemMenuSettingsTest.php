<?php

namespace Tests\Unit;

use App\Http\Controllers\SystemSettingsController;
use App\Models\DefaultSettings;
use App\Models\Menu;
use App\Models\User;
use App\Services\Auth\UserSessionInvalidationService;
use App\Services\Install\InstallSchema;
use App\Services\MenuManagerService;
use App\Services\MenuSelectionService;
use App\Services\Settings\SystemSettingsSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class SystemMenuSettingsTest extends TestCase
{
    private string $account;
    private Menu $english;
    private Menu $russian;
    private array $nativeSession;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'system_menu_test',
            'database.connections.system_menu_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        DB::purge('system_menu_test');
        (new ReflectionMethod(InstallSchema::class, 'ensureMenuSchema'))->invoke(new InstallSchema);
        foreach (['default', 'domain', 'user'] as $scope) {
            Schema::create("v_{$scope}_settings", function (Blueprint $table) use ($scope) {
                $table->uuid("{$scope}_setting_uuid")->primary();
                $table->uuid('domain_uuid')->nullable();
                $table->uuid('user_uuid')->nullable();
                foreach (['category', 'subcategory', 'name', 'value', 'order', 'description'] as $field) {
                    $table->text("{$scope}_setting_{$field}")->nullable();
                }
                $table->boolean("{$scope}_setting_enabled")->default(true);
            });
        }
        $this->english = Menu::create(['menu_name' => 'fspbx', 'menu_language' => 'en-us']);
        $this->russian = Menu::create(['menu_name' => 'Russian custom', 'menu_language' => 'ru']);
        $this->account = (string) Str::uuid();
        $this->actingAs((new User)->forceFill(['user_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->account]));
        session()->start();
        session()->put('permissions', [(object) ['permission_name' => 'default_setting_edit']]);
        $this->nativeSession = $_SESSION ?? [];
        Route::put('/test/system-menu', [SystemSettingsController::class, 'update']);
        app()->setLocale('en-us');
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->nativeSession;
        DB::disconnect('system_menu_test');
        parent::tearDown();
    }

    public function test_picker_lists_names_and_languages_and_reads_the_exact_global_setting(): void
    {
        $this->setting('default', $this->russian);
        $this->setting('default', $this->english, ['default_setting_category' => 'other']);
        $this->setting('default', $this->english, ['default_setting_name' => 'text']);
        $schema = app(SystemSettingsSchema::class);
        $field = collect($schema->fields())->firstWhere('key', 'menu');
        $this->assertSame(['domain', 'menu', 'uuid'], [$field['category'], $field['subcategory'], $field['name']]);
        $options = collect($schema->options()['menus'])->keyBy('value');
        $this->assertSame('fspbx · English (en-us)', $options[$this->english->menu_uuid]['label']);
        $this->assertSame('Russian custom · Русский (ru)', $options[$this->russian->menu_uuid]['label']);
        $this->assertSame($this->russian->menu_uuid, $schema->values()['menu']);
    }

    public function test_save_updates_only_the_global_menu_and_preserves_overrides_and_metadata(): void
    {
        $uuid = $this->setting('default', $this->english, [
            'default_setting_order' => null, 'default_setting_description' => 'Existing description',
        ]);
        $otherCategory = $this->setting('default', $this->english, ['default_setting_category' => 'other']);
        $otherType = $this->setting('default', $this->english, ['default_setting_name' => 'text']);
        $this->setting('domain', $this->english);
        $this->setting('user', $this->english);
        $overrides = [DB::table('v_domain_settings')->get()->toJson(), DB::table('v_user_settings')->get()->toJson()];
        session()->put('user.menu_uuid', $this->english->menu_uuid);
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $row = DefaultSettings::findOrFail($uuid);
        $this->assertSame($this->russian->menu_uuid, $row->default_setting_value);
        $this->assertNull($row->default_setting_order);
        $this->assertSame('Existing description', $row->default_setting_description);
        foreach ([$otherCategory, $otherType] as $unrelated) {
            $this->assertSame($this->english->menu_uuid, DefaultSettings::findOrFail($unrelated)->default_setting_value);
        }
        $this->assertSame($overrides, [DB::table('v_domain_settings')->get()->toJson(), DB::table('v_user_settings')->get()->toJson()]);
        $this->assertSame($this->english->menu_uuid, session('user.menu_uuid'));
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $this->assertSame(3, DefaultSettings::count());
    }

    public function test_missing_setting_displays_the_runtime_fallback_and_save_creates_the_default(): void
    {
        $this->assertSame($this->english->menu_uuid, app(SystemSettingsSchema::class)->values()['menu']);
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $this->assertDatabaseHas('v_default_settings', [
            'default_setting_category' => 'domain', 'default_setting_subcategory' => 'menu',
            'default_setting_name' => 'uuid', 'default_setting_value' => $this->russian->menu_uuid,
            'default_setting_enabled' => true,
        ]);
    }

    public static function invalidMenus(): array
    {
        return [[null], [''], ['not-a-uuid'], ['00000000-0000-0000-0000-000000000000'], [['invalid']]];
    }

    /** @dataProvider invalidMenus */
    public function test_invalid_menu_selections_are_rejected_without_changing_the_default($value): void
    {
        $uuid = $this->setting('default', $this->english);
        $this->postMenu($value)->assertUnprocessable()->assertJsonValidationErrors('settings.menu');
        $this->assertSame($this->english->menu_uuid, DefaultSettings::findOrFail($uuid)->default_setting_value);
    }

    public function test_deleted_choices_and_users_without_edit_permission_cannot_change_the_default(): void
    {
        $uuid = $this->setting('default', $this->english);
        session()->put('permissions', []);
        $this->postMenu($this->russian->menu_uuid)->assertForbidden();
        session()->put('permissions', [(object) ['permission_name' => 'default_setting_edit']]);
        $this->russian->delete();
        $this->postMenu($this->russian->menu_uuid)->assertUnprocessable();
        $this->assertSame($this->english->menu_uuid, DefaultSettings::findOrFail($uuid)->default_setting_value);
    }

    public function test_account_override_precedes_system_default_and_stale_or_disabled_overrides_fall_back(): void
    {
        $this->setting('default', $this->russian);
        $uuid = $this->setting('domain', $this->english);
        $selector = app(MenuSelectionService::class);
        $this->assertTrue($selector->forAccount($this->account)->is($this->english));
        $this->assertTrue($selector->forAccount((string) Str::uuid())->is($this->russian));
        DB::table('v_domain_settings')->where('domain_setting_uuid', $uuid)->update(['domain_setting_enabled' => false]);
        $this->assertTrue($selector->forAccount($this->account)->is($this->russian));
        DB::table('v_domain_settings')->where('domain_setting_uuid', $uuid)->update([
            'domain_setting_enabled' => true, 'domain_setting_value' => (string) Str::uuid(),
        ]);
        $this->assertTrue($selector->forAccount($this->account)->is($this->russian));
        $this->russian->delete();
        $this->assertTrue($selector->forAccount($this->account)->is($this->english));
        $this->english->delete();
        $this->assertNull($selector->forAccount($this->account));
    }

    public function test_session_refresh_ignores_legacy_user_overrides(): void
    {
        $this->setting('default', $this->russian);
        $this->setting('user', $this->english);
        request()->setLaravelSession(app('session.store'));
        session()->put('user.groups', [(object) ['group_uuid' => (string) Str::uuid()]]);
        app(UserSessionInvalidationService::class)->refreshCurrentUserMenuSession();
        $this->assertSame($this->russian->menu_uuid, session('user.menu_uuid'));
        $this->assertSame($this->russian->menu_uuid, $_SESSION['domain']['menu']['uuid']);
        $this->setting('domain', $this->english);
        app(UserSessionInvalidationService::class)->refreshCurrentUserMenuSession();
        $this->assertSame($this->english->menu_uuid, session('user.menu_uuid'));
    }

    public function test_menu_referenced_only_by_an_unused_user_override_can_be_deleted(): void
    {
        $this->setting('user', $this->russian);
        app(MenuManagerService::class)->deleteMenu($this->russian);
        $this->assertNull(Menu::find($this->russian->menu_uuid));
        $this->assertSame(1, DB::table('v_user_settings')->count());
    }

    public function test_system_and_account_menu_assignments_still_prevent_deletion(): void
    {
        $this->setting('default', $this->russian);
        $this->setting('domain', $this->english);
        foreach ([$this->russian, $this->english] as $menu) {
            try {
                app(MenuManagerService::class)->deleteMenu($menu);
                $this->fail('Assigned menus must be protected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('menu', $exception->errors());
            }
            $this->assertNotNull($menu->fresh());
        }
    }

    private function postMenu($value)
    {
        return $this->putJson('/test/system-menu', ['settings' => ['menu' => $value]]);
    }

    private function setting(string $scope, Menu $menu, array $extra = []): string
    {
        $uuid = (string) Str::uuid();
        DB::table("v_{$scope}_settings")->insert([
            "{$scope}_setting_uuid" => $uuid, "{$scope}_setting_category" => 'domain',
            "{$scope}_setting_subcategory" => 'menu', "{$scope}_setting_name" => 'uuid',
            "{$scope}_setting_value" => $menu->menu_uuid, "{$scope}_setting_enabled" => true,
            'domain_uuid' => $scope === 'domain' ? $this->account : null,
            'user_uuid' => $scope === 'user' ? auth()->user()->user_uuid : null,
            ...$extra,
        ]);

        return $uuid;
    }
}
