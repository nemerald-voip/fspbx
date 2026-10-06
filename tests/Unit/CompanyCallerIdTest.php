<?php

namespace Tests\Unit;

use App\Http\Controllers\CompanyCallerIdController;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\FusionCache;
use App\Services\CompanyCallerIdService;
use App\Services\DialplanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CompanyCallerIdTest extends TestCase
{
    private CompanyCallerIdService $service;
    private Domain $domain;
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->createSchema();
        $this->cachePath = sys_get_temp_dir() . '/company-caller-id-' . Str::uuid();
        mkdir($this->cachePath);
        (new ReflectionProperty(FusionCache::class, 'cacheType'))->setValue(null, 'file');
        (new ReflectionProperty(FusionCache::class, 'cacheLocation'))->setValue(null, $this->cachePath);

        $this->domain = (new Domain())->forceFill(['domain_uuid' => (string) Str::uuid(), 'domain_name' => 'company.example.com']);
        DB::table('v_domains')->insert($this->domain->getAttributes());
        session(['domain_uuid' => $this->domain->domain_uuid, 'domain_name' => $this->domain->domain_name]);
        $this->permissions('extension_edit');
        $this->service = app(CompanyCallerIdService::class);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(FusionCache::class, 'cacheType'))->setValue(null, null);
        (new ReflectionProperty(FusionCache::class, 'cacheLocation'))->setValue(null, null);
        foreach (glob($this->cachePath . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->cachePath);
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_extension_admin_can_set_up_missing_defaults_without_dialplan_permissions(): void
    {
        $options = $this->service->options($this->domain);
        $this->assertSame('missing', $options['status']);
        $this->assertTrue($options['can_manage']);
        $this->save();

        $plan = Dialplans::firstOrFail();
        $this->assertSame($this->domain->domain_uuid, $plan->domain_uuid);
        $this->assertSame($this->domain->domain_name, $plan->dialplan_context);
        $this->assertSame('3b290b41-cec8-467d-8b12-d51cdc74a81e', $plan->app_uuid);
        $this->assertSame(19, (int) $plan->dialplan_order);
        $this->assertTrue($plan->dialplan_enabled);
        $this->assertSame('true', $plan->dialplan_continue);
        $this->assertCount(4, $plan->dialplan_details);
        $this->assertStringContainsString('default_outbound_caller_id_number=+13105550100', $plan->dialplan_xml);
        $this->assertStringContainsString('default_emergency_caller_id_number=+13105550101', $plan->dialplan_xml);
        $this->assertSame('ready', $this->service->options($this->domain)['status']);
    }

    public function test_updates_keep_identity_and_use_the_same_xml_and_cache_path_as_dialplan_manager(): void
    {
        $this->save();
        $original = Dialplans::firstOrFail();
        $original->update(['dialplan_description' => 'Keep this description', 'dialplan_order' => 20]);
        foreach (['dialplan.company.example.com', 'dialplan.company.example.com.1000', 'dialplan.other.example.com'] as $key) {
            file_put_contents($this->cachePath . '/' . $key, 'cached');
        }

        DB::transaction(function () {
            $this->save(['outbound_caller_id_number' => '+442079460000']);
            $this->assertFileExists($this->cachePath . '/dialplan.company.example.com');
        });

        $plan = $original->fresh();
        $this->assertSame(1, Dialplans::count());
        $this->assertSame($original->app_uuid, $plan->app_uuid);
        $this->assertSame('Keep this description', $plan->dialplan_description);
        $this->assertSame(20, (int) $plan->dialplan_order);
        $service = app(DialplanService::class);
        $this->assertSame($service->buildXml($plan, $service->normalizedDetails($plan->dialplan_details->toArray())), $plan->dialplan_xml);
        $this->assertFileDoesNotExist($this->cachePath . '/dialplan.company.example.com');
        $this->assertFileDoesNotExist($this->cachePath . '/dialplan.company.example.com.1000');
        $this->assertFileExists($this->cachePath . '/dialplan.other.example.com');
    }

    public function test_preview_reads_saved_xml_instead_of_stale_builder_rows(): void
    {
        $this->save();
        $plan = Dialplans::firstOrFail();
        $plan->update(['dialplan_xml' => str_replace('+13105550100', '+61255550100', $plan->dialplan_xml)]);
        $this->assertSame('+61255550100', $this->service->options($this->domain)['values']['outbound_caller_id_number']);
    }

    public function test_blank_stock_template_can_be_configured_and_disabled_defaults_are_explicit(): void
    {
        $this->save(['outbound_caller_id_number' => '', 'emergency_caller_id_number' => '']);
        $plan = Dialplans::firstOrFail();
        $plan->update(['dialplan_enabled' => false]);
        $options = $this->service->options($this->domain);
        $this->assertSame('disabled', $options['status']);
        $this->assertNull($options['values']['outbound_caller_id_number']);
        $this->save();
        $this->assertTrue($plan->fresh()->dialplan_enabled);
    }

    public function test_another_accounts_default_is_neither_exposed_nor_changed(): void
    {
        $this->save();
        $plan = Dialplans::firstOrFail();
        $plan->update(['domain_uuid' => (string) Str::uuid(), 'dialplan_context' => 'other.example.com']);
        $options = $this->service->options($this->domain);
        $this->assertSame('missing', $options['status']);
        $this->assertNull($options['values']['outbound_caller_id_number']);
        $this->save(['outbound_caller_id_number' => '+442079460000']);
        $this->assertSame(2, Dialplans::count());
        $this->assertSame($plan->dialplan_xml, $plan->fresh()->dialplan_xml);
    }

    public function test_shared_defaults_are_visible_but_cannot_be_changed_by_the_account_shortcut(): void
    {
        $this->save();
        $plan = Dialplans::firstOrFail();
        $plan->update(['domain_uuid' => null, 'dialplan_context' => 'global']);
        $options = $this->service->options($this->domain);
        $this->assertSame('shared', $options['status']);
        $this->assertSame('+13105550100', $options['values']['outbound_caller_id_number']);
        $this->assertFalse($options['can_manage']);
        $this->expectException(ValidationException::class);
        $this->save();
    }

    public function test_conditional_xml_is_not_presented_as_a_fixed_company_number_or_overwritten(): void
    {
        $this->save();
        $plan = Dialplans::firstOrFail();
        $plan->update(['dialplan_xml' => str_replace('field="" expression=""', 'field="destination_number" expression="^911$"', $plan->dialplan_xml)]);
        $options = $this->service->options($this->domain);
        $this->assertSame('custom', $options['status']);
        $this->assertNull($options['values']['outbound_caller_id_number']);
        $this->assertFalse($options['can_manage']);
        try {
            $this->save();
            $this->fail('Custom rules must not be overwritten.');
        } catch (ValidationException $e) {
            $this->assertSame($plan->dialplan_xml, $plan->fresh()->dialplan_xml);
        }
    }

    public function test_duplicate_defaults_require_the_full_editor(): void
    {
        $this->save();
        $copy = Dialplans::firstOrFail()->replicate();
        $copy->dialplan_uuid = (string) Str::uuid();
        $copy->save();
        $this->assertSame('custom', $this->service->options($this->domain)['status']);
        $this->expectException(ValidationException::class);
        $this->save();
    }

    public function test_disabled_custom_builder_rows_are_not_deleted_by_the_shortcut(): void
    {
        $this->save();
        $plan = Dialplans::firstOrFail();
        $plan->dialplan_details()->create([
            'dialplan_detail_tag' => 'action',
            'dialplan_detail_type' => 'log',
            'dialplan_detail_data' => 'Keep this custom action',
            'dialplan_detail_enabled' => 'false',
        ]);
        $this->assertSame('custom', $this->service->options($this->domain)['status']);
        $this->expectException(ValidationException::class);
        $this->save();
    }

    public function test_stale_edits_are_rejected_including_two_concurrent_initial_setups(): void
    {
        $revision = $this->service->options($this->domain)['revision'];
        $this->save();
        $this->expectException(ValidationException::class);
        $this->save(['revision' => $revision]);
    }

    public function test_extension_self_service_cannot_change_company_defaults_even_with_dialplan_access(): void
    {
        $this->permissions('outbound_caller_id_number', 'dialplan_edit', 'dialplan_add');
        $this->assertFalse($this->service->options($this->domain)['can_manage']);
        $this->expectException(HttpException::class);
        $this->save();
    }

    public function test_controller_rejects_a_different_account_even_for_an_extension_admin(): void
    {
        $other = (new Domain())->forceFill(['domain_uuid' => (string) Str::uuid(), 'domain_name' => 'other.example.com']);
        $this->expectException(HttpException::class);
        app(CompanyCallerIdController::class)->update(Request::create('/', 'PUT'), $other, $this->service);
    }

    public function test_controller_validates_literal_numbers_and_names_on_the_server(): void
    {
        $this->expectException(ValidationException::class);
        app(CompanyCallerIdController::class)->update(Request::create('/', 'PUT', [
            'revision' => $this->service->options($this->domain)['revision'],
            'outbound_caller_id_number' => '${some_variable}',
            'outbound_caller_id_name' => '${system(command)}',
        ]), $this->domain, $this->service);
    }

    public function test_controller_accepts_extension_admin_and_returns_updated_defaults(): void
    {
        $response = app(CompanyCallerIdController::class)->update(Request::create('/', 'PUT', [
            'revision' => $this->service->options($this->domain)['revision'],
            'outbound_caller_id_number' => '+13105550199',
        ]), $this->domain, $this->service);
        $this->assertSame(200, $response->getStatusCode());
        $options = $response->getData(true)['company_caller_id'];
        $this->assertSame('ready', $options['status']);
        $this->assertSame('+13105550199', $options['values']['outbound_caller_id_number']);
        $this->assertNull($options['values']['emergency_caller_id_number']);
    }

    public function test_controller_accepts_blank_numbers_to_clear_the_company_caller_id(): void
    {
        $this->save();
        $response = app(CompanyCallerIdController::class)->update(Request::create('/', 'PUT', [
            'revision' => $this->service->options($this->domain)['revision'],
            'outbound_caller_id_number' => '',
            'emergency_caller_id_number' => '',
        ]), $this->domain, $this->service);
        $this->assertSame(200, $response->getStatusCode());
        $options = $response->getData(true)['company_caller_id'];
        $this->assertSame('ready', $options['status']);
        $this->assertNull($options['values']['outbound_caller_id_number']);
        $this->assertNull($options['values']['emergency_caller_id_number']);
    }

    private function save(array $overrides = []): void
    {
        $this->service->save($this->domain, array_merge([
            'revision' => $this->service->options($this->domain)['revision'],
            'outbound_caller_id_number' => '+13105550100',
            'emergency_caller_id_number' => '+13105550101',
            'outbound_caller_id_name' => 'Company & Co',
            'emergency_caller_id_name' => '',
        ], $overrides));
    }

    private function permissions(string ...$names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function createSchema(): void
    {
        Schema::create('v_domains', function (Blueprint $table) {
            $table->string('domain_uuid')->primary();
            $table->string('domain_name');
        });
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->string('dialplan_uuid')->primary();
            foreach (['domain_uuid', 'app_uuid', 'hostname', 'dialplan_name', 'dialplan_destination', 'dialplan_number', 'dialplan_context', 'dialplan_continue', 'dialplan_enabled', 'insert_user', 'update_user'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('dialplan_xml')->nullable();
            $table->text('dialplan_description')->nullable();
            $table->integer('dialplan_order')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->timestamp('update_date')->nullable();
        });
        Schema::create('v_dialplan_details', function (Blueprint $table) {
            $table->string('dialplan_detail_uuid')->primary();
            foreach (['domain_uuid', 'dialplan_uuid', 'dialplan_detail_tag', 'dialplan_detail_type', 'dialplan_detail_break', 'dialplan_detail_inline', 'dialplan_detail_enabled', 'insert_user', 'update_user'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('dialplan_detail_data')->nullable();
            $table->integer('dialplan_detail_group')->nullable();
            $table->integer('dialplan_detail_order')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->timestamp('update_date')->nullable();
        });
    }
}
