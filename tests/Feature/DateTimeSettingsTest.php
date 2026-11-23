<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\SystemSettingsController;
use App\Models\Domain;
use App\Models\User;
use App\Services\Settings\AccountSettingsSchema;
use App\Services\Settings\SystemSettingsSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DateTimeSettingsTest extends TestCase
{
    private Domain $account;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'regional_test',
            'database.connections.regional_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        Event::fake(['eloquent.created: '.Domain::class, 'eloquent.updated: '.Domain::class]);
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name');
            $table->string('domain_description');
            $table->boolean('domain_enabled');
        });
        Schema::create('v_menus', function (Blueprint $table) {
            $table->uuid('menu_uuid')->primary();
            $table->string('menu_name');
            $table->string('menu_language');
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
        $this->account = Domain::create(['domain_name' => 'a.test', 'domain_description' => 'A', 'domain_enabled' => true]);
        $this->actingAs((new User)->forceFill(['user_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->account->domain_uuid]));
        session()->put('domain_uuid', $this->account->domain_uuid);
        session()->put('permissions', [
            (object) ['permission_name' => 'default_setting_edit'],
            (object) ['permission_name' => 'account_settings_list_view'],
        ]);
        Route::put('/test/global-formats', [SystemSettingsController::class, 'update']);
        Route::put('/test/account-formats', [AccountSettingsController::class, 'update']);
    }

    private function saveAccount(array $settings)
    {
        return $this->putJson('/test/account-formats', [
            ...$this->account->only('domain_uuid', 'domain_name', 'domain_description', 'domain_enabled'),
            'settings' => $settings,
        ]);
    }

    public function test_pickers_save_and_revert_independent_overrides_with_warm_caches(): void
    {
        $account = $this->account->domain_uuid;
        $other = Domain::create(['domain_name' => 'b.test', 'domain_description' => 'B', 'domain_enabled' => true])->domain_uuid;
        $system = app(SystemSettingsSchema::class);
        $schema = app(AccountSettingsSchema::class);
        $this->assertSame('us', $system->values()['date_format']);
        $this->assertSame('12h', $system->values()['time_format']);
        $this->assertNull($schema->values($this->account)['date_format']);
        $this->assertSame('31/01/2026', $system->options()['date_format']['eu']);
        $this->assertSame('15:04', $schema->options($this->account)['time_format']['24h']);

        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(), 'domain_uuid' => $account,
            'domain_setting_category' => 'custom', 'domain_setting_subcategory' => 'date_format',
            'domain_setting_name' => 'text', 'domain_setting_value' => 'iso', 'domain_setting_enabled' => true,
        ]);
        foreach ([$account, $other] as $uuid) {
            $this->assertSame('g:i:s A M d, Y', local_datetime_format($uuid));
        }
        $this->putJson('/test/global-formats', ['settings' => ['date_format' => 'eu', 'time_format' => '24h']])->assertOk();
        $this->assertSame('d/m/Y H:i:s', local_datetime_format($account));
        $this->saveAccount(['date_format' => 'iso'])->assertOk();
        $this->assertSame('Y-m-d H:i:s', local_datetime_format($account));
        $this->assertSame('d/m/Y H:i:s', local_datetime_format($other));
        $this->saveAccount(['date_format' => null, 'time_format' => '12h'])->assertOk();
        $this->assertNull($schema->values($this->account)['date_format']);
        $this->assertSame('d/m/Y g:i:s A', local_datetime_format($account));
        $this->assertSame('d/m/Y H:i:s', local_datetime_format($other));
    }

    public function test_disabled_global_format_can_be_changed_and_enabled(): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(), 'default_setting_category' => 'domain',
            'default_setting_subcategory' => 'time_format', 'default_setting_name' => 'text',
            'default_setting_value' => '24h', 'default_setting_enabled' => false,
        ]);
        $this->assertSame('12h', app(SystemSettingsSchema::class)->values()['time_format']);
        $this->putJson('/test/global-formats', ['settings' => ['time_format' => '24h']])->assertOk();
        $this->assertSame('H:i:s', local_time_format($this->account->domain_uuid));
    }

    public function test_invalid_options_and_empty_system_defaults_are_rejected(): void
    {
        $this->putJson('/test/global-formats', ['settings' => ['date_format' => 'uk', 'time_format' => null]])
            ->assertUnprocessable()->assertJsonValidationErrors(['settings.date_format', 'settings.time_format']);
        $this->saveAccount(['date_format' => 'uk', 'time_format' => 'military'])
            ->assertUnprocessable()->assertJsonValidationErrors(['settings.date_format', 'settings.time_format']);
    }
}
