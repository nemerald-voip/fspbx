<?php

namespace Tests\Unit;

use App\Http\Controllers\AccountSettingsController;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\Menu;
use App\Models\User;
use App\Services\Install\InstallSchema;
use App\Services\MenuSelectionService;
use App\Services\Settings\AccountSettingsSchema;
use App\Services\Settings\SystemSettingsSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class AccountMenuSettingsTest extends TestCase
{
    private Domain $account;
    private Menu $english;
    private Menu $russian;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'account_menu_test',
            'database.connections.account_menu_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        DB::purge('account_menu_test');
        // Domain provisioning and FreeSWITCH are unrelated to saving this setting.
        Event::fake(['eloquent.created: '.Domain::class, 'eloquent.updated: '.Domain::class]);
        (new ReflectionMethod(InstallSchema::class, 'ensureMenuSchema'))->invoke(new InstallSchema);
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name');
            $table->string('domain_description');
            $table->boolean('domain_enabled');
        });
        foreach (['default', 'domain'] as $scope) {
            Schema::create("v_{$scope}_settings", function (Blueprint $table) use ($scope) {
                $table->uuid("{$scope}_setting_uuid")->primary();
                $table->uuid('domain_uuid')->nullable();
                foreach (['category', 'subcategory', 'name', 'value', 'order', 'description'] as $field) {
                    $table->text("{$scope}_setting_{$field}")->nullable();
                }
                $table->boolean("{$scope}_setting_enabled")->default(true);
            });
        }
        $this->account = Domain::create([
            'domain_name' => 'menu-test.example', 'domain_description' => 'Menu Test', 'domain_enabled' => true,
        ]);
        $this->english = Menu::create(['menu_name' => 'fspbx', 'menu_language' => 'en-us']);
        $this->russian = Menu::create(['menu_name' => 'Support', 'menu_language' => 'ru']);
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(), 'default_setting_category' => 'domain',
            'default_setting_subcategory' => 'menu', 'default_setting_name' => 'uuid',
            'default_setting_value' => $this->english->menu_uuid, 'default_setting_enabled' => true,
        ]);
        $this->actingAs((new User)->forceFill([
            'user_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->account->domain_uuid,
        ]));
        session()->start();
        session()->put('domain_uuid', $this->account->domain_uuid);
        session()->put('permissions', [(object) ['permission_name' => 'account_settings_list_view']]);
        Route::put('/test/account-menu', [AccountSettingsController::class, 'update']);
        app()->setLocale('en-us');
    }

    protected function tearDown(): void
    {
        DB::disconnect('account_menu_test');
        parent::tearDown();
    }

    public function test_picker_shares_menu_options_and_shows_only_the_exact_active_account_override(): void
    {
        $schema = app(AccountSettingsSchema::class);
        $this->assertSame(app(SystemSettingsSchema::class)->options()['menus'], $schema->options($this->account)['menus']);
        $this->assertNull($schema->values($this->account)['menu']);
        $this->setting($this->english, ['domain_setting_category' => 'other']);
        $this->setting($this->english, ['domain_setting_name' => 'text']);
        $this->setting($this->english, ['domain_uuid' => (string) Str::uuid()]);
        $uuid = $this->setting($this->russian);
        $this->assertSame($this->russian->menu_uuid, $schema->values($this->account)['menu']);
        DB::table('v_domain_settings')->where('domain_setting_uuid', $uuid)->update(['domain_setting_enabled' => false]);
        $this->assertNull($schema->values($this->account)['menu']);
        DB::table('v_domain_settings')->where('domain_setting_uuid', $uuid)->update([
            'domain_setting_enabled' => true, 'domain_setting_value' => (string) Str::uuid(),
        ]);
        $this->assertNull($schema->values($this->account)['menu']);
    }

    public function test_save_creates_an_override_without_changing_the_default_or_active_session(): void
    {
        session()->put('user.menu_uuid', $this->english->menu_uuid);
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $this->assertSame($this->russian->menu_uuid, app(AccountSettingsSchema::class)->values($this->account)['menu']);
        $this->assertSame($this->russian->menu_uuid, app(MenuSelectionService::class)->forAccount($this->account->domain_uuid)->menu_uuid);
        $this->assertSame($this->english->menu_uuid, app(MenuSelectionService::class)->systemDefault()->menu_uuid);
        $this->assertSame($this->english->menu_uuid, session('user.menu_uuid'));
    }

    public function test_update_preserves_other_accounts_settings_and_metadata_and_repeated_save_is_unchanged(): void
    {
        $uuid = $this->setting($this->english, ['domain_setting_description' => 'Custom description']);
        $otherIds = [
            $this->setting($this->english, ['domain_setting_category' => 'other']),
            $this->setting($this->english, ['domain_setting_name' => 'text']),
            $this->setting($this->english, ['domain_uuid' => (string) Str::uuid()]),
        ];
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $row = DomainSettings::findOrFail($uuid);
        $this->assertSame($this->russian->menu_uuid, $row->domain_setting_value);
        $this->assertNull($row->domain_setting_order);
        $this->assertSame('Custom description', $row->domain_setting_description);
        foreach ($otherIds as $id) {
            $this->assertSame($this->english->menu_uuid, DomainSettings::findOrFail($id)->domain_setting_value);
        }
        $before = DomainSettings::all()->toJson();
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $this->assertSame($before, DomainSettings::all()->toJson());
    }

    public static function emptyValues(): array
    {
        return [[null], ['']];
    }

    /** @dataProvider emptyValues */
    public function test_clearing_removes_only_this_override_and_restores_inheritance($value): void
    {
        $uuid = $this->setting($this->russian);
        $otherId = $this->setting($this->russian, ['domain_uuid' => (string) Str::uuid()]);
        $otherType = $this->setting($this->russian, ['domain_setting_name' => 'text']);
        $this->postMenu($value)->assertOk();
        $this->assertNull(DomainSettings::find($uuid));
        $this->assertNotNull(DomainSettings::find($otherId));
        $this->assertNotNull(DomainSettings::find($otherType));
        $this->assertNull(app(AccountSettingsSchema::class)->values($this->account)['menu']);
        $this->assertSame($this->english->menu_uuid, app(MenuSelectionService::class)->forAccount($this->account->domain_uuid)->menu_uuid);
    }

    public function test_explicitly_selecting_the_current_default_remains_an_override_when_the_default_changes(): void
    {
        $this->postMenu($this->english->menu_uuid)->assertOk();
        DB::table('v_default_settings')->update(['default_setting_value' => $this->russian->menu_uuid]);
        $this->assertSame($this->english->menu_uuid, app(MenuSelectionService::class)->forAccount($this->account->domain_uuid)->menu_uuid);
        $this->postMenu(null)->assertOk();
        $this->assertSame($this->russian->menu_uuid, app(MenuSelectionService::class)->forAccount($this->account->domain_uuid)->menu_uuid);
    }

    public function test_selecting_a_disabled_override_reenables_its_existing_row(): void
    {
        $uuid = $this->setting($this->russian, ['domain_setting_enabled' => false]);
        $this->postMenu($this->russian->menu_uuid)->assertOk();
        $this->assertEquals(true, DomainSettings::findOrFail($uuid)->domain_setting_enabled);
        $this->assertSame(1, DomainSettings::count());
    }

    public static function invalidMenus(): array
    {
        return [['not-a-menu'], ['00000000-0000-0000-0000-000000000000'], [['invalid']]];
    }

    /** @dataProvider invalidMenus */
    public function test_invalid_selections_leave_existing_settings_untouched($value): void
    {
        $uuid = $this->setting($this->english);
        $this->postMenu($value)->assertUnprocessable()->assertJsonValidationErrors('settings.menu');
        $this->assertSame($this->english->menu_uuid, DomainSettings::findOrFail($uuid)->domain_setting_value);
    }

    public function test_deleted_choices_and_unauthorized_or_cross_account_requests_cannot_save(): void
    {
        $this->russian->delete();
        $this->postMenu($this->russian->menu_uuid)->assertUnprocessable();
        $this->postMenu($this->english->menu_uuid, ['domain_uuid' => (string) Str::uuid()])->assertForbidden();
        session()->put('permissions', []);
        $this->postMenu($this->english->menu_uuid)->assertForbidden();
        auth()->logout();
        $this->postMenu($this->english->menu_uuid)->assertForbidden();
        $this->assertSame(0, DomainSettings::count());
    }

    private function postMenu($value, array $extra = [])
    {
        return $this->putJson('/test/account-menu', [
            'domain_uuid' => $this->account->domain_uuid, 'domain_name' => $this->account->domain_name,
            'domain_description' => $this->account->domain_description, 'domain_enabled' => true,
            'settings' => ['menu' => $value], ...$extra,
        ]);
    }

    private function setting(Menu $menu, array $extra = []): string
    {
        $uuid = (string) Str::uuid();
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => $uuid, 'domain_uuid' => $this->account->domain_uuid,
            'domain_setting_category' => 'domain', 'domain_setting_subcategory' => 'menu',
            'domain_setting_name' => 'uuid', 'domain_setting_value' => $menu->menu_uuid,
            'domain_setting_enabled' => true, ...$extra,
        ]);

        return $uuid;
    }
}
