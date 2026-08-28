<?php

namespace Tests\Unit;

use App\Http\Requests\StoreDynamicRouteRequest;
use App\Models\Dialplans;
use App\Models\DynamicRoute;
use App\Models\DynamicRouteRule;
use App\Services\CallRoutingOptionsService;
use App\Services\DialplanService;
use App\Services\DynamicRouteService;
use App\Services\PhoneNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicRouteDialplanTest extends TestCase
{
    public function test_dynamic_routes_are_first_class_transfer_destinations(): void
    {
        $routingType = collect(app(CallRoutingOptionsService::class)->routingTypes)
            ->firstWhere('value', 'dynamic_routes');

        $this->assertSame('Dynamic Route', $routingType['name'] ?? null);
        $this->assertSame([
            'destination_app' => 'transfer',
            'destination_data' => '9500 XML account.example.com',
        ], buildDestinationAction([
            'type' => 'dynamic_routes',
            'extension' => '9500',
        ], 'account.example.com'));
    }

    public function test_equivalent_phone_number_formats_are_duplicate_match_values(): void
    {
        app()->instance(PhoneNumberService::class, new class extends PhoneNumberService
        {
            public function countryCodeForDomain(?string $domainUuid = null): string
            {
                return 'US';
            }
        });

        $request = StoreDynamicRouteRequest::create('/dynamic-routes', 'POST', [
            'default_destination_type' => 'hangup',
            'rules' => [
                [
                    'match_value' => '5304792220',
                    'destination_type' => 'hangup',
                ],
                [
                    'match_value' => '+15304792220',
                    'destination_type' => 'hangup',
                ],
            ],
        ]);
        $request->setContainer(app());

        $validator = app('validator')->make([], []);
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertSame(
            'Each match value must be unique.',
            $validator->errors()->first('rules.1.match_value')
        );
    }

    public function test_original_did_rules_compile_to_native_dialplan_conditions(): void
    {
        $dynamicRoute = new DynamicRoute([
            'domain_uuid' => '1e7080d8-1b52-412b-827d-9b91c7856c22',
            'dialplan_uuid' => '28a501e9-3e73-46cc-a8bc-a5cb04f828a2',
            'name' => 'Main DID map',
            'extension' => '9500',
            'source' => DynamicRoute::SOURCE_CALLER_DESTINATION,
            'context' => 'account.example.com',
            'default_destination_type' => 'hangup',
            'enabled' => true,
        ]);
        $dynamicRoute->dynamic_route_uuid = '2ffb347d-75b3-4bb2-9a80-35601a67f103';

        $dynamicRoute->setRelation('rules', collect([
            new DynamicRouteRule([
                'match_value' => '+15304792220',
                'destination_type' => 'extensions',
                'destination_value' => '1001',
                'rule_order' => 0,
            ]),
            new DynamicRouteRule([
                'match_value' => '9005',
                'destination_type' => 'voicemails',
                'destination_value' => '1002',
                'rule_order' => 1,
            ]),
        ]));

        $phoneNumbers = new class extends PhoneNumberService
        {
            public function countryCodeForDomain(?string $domainUuid = null): string
            {
                return 'US';
            }
        };
        $details = (new DynamicRouteService($phoneNumbers))->buildDetails($dynamicRoute);
        $dialplan = new Dialplans([
            'dialplan_uuid' => $dynamicRoute->dialplan_uuid,
            'dialplan_name' => 'Dynamic Route: Main DID map',
            'dialplan_context' => $dynamicRoute->context,
            'dialplan_continue' => 'false',
        ]);
        $xml = app(DialplanService::class)->buildXml($dialplan, $details);

        $this->assertStringStartsWith('<extension name="Dynamic Route: Main DID map"', $xml);
        $this->assertSame(1, substr_count($xml, 'field="destination_number" expression="^9500$"'));
        $this->assertStringContainsString('<condition field="destination_number" expression="^9500$"/>', $xml);
        $this->assertStringContainsString('field="${caller_destination}"', $xml);
        $this->assertStringContainsString('expression="^(?:\\+15304792220|15304792220|5304792220)$" break="on-true"', $xml);
        $this->assertStringContainsString('expression="^9005$" break="on-true"', $xml);
        $this->assertStringContainsString('application="transfer" data="1001 XML account.example.com"', $xml);
        $this->assertStringContainsString('application="transfer" data="*991002 XML account.example.com"', $xml);
        $this->assertStringContainsString('<condition field="" expression="">', $xml);
        $this->assertStringContainsString('application="hangup" data=""', $xml);
        $this->assertStringContainsString('dynamic_route_uuid=2ffb347d-75b3-4bb2-9a80-35601a67f103', $xml);
        $this->assertStringNotContainsString('break="never"', $xml);
        $this->assertStringNotContainsString('lua', $xml);
        $this->assertStringNotContainsString('sql', strtolower($xml));

        $extensionGuard = strpos($xml, 'field="destination_number" expression="^9500$"');
        $firstRule = strpos($xml, '^(?:\\+15304792220|15304792220|5304792220)$');
        $secondRule = strpos($xml, '^9005$');
        $fallback = strrpos($xml, 'application="hangup"');

        $this->assertIsInt($extensionGuard);
        $this->assertIsInt($firstRule);
        $this->assertIsInt($secondRule);
        $this->assertIsInt($fallback);
        $this->assertLessThan($firstRule, $extensionGuard);
        $this->assertLessThan($secondRule, $firstRule);
        $this->assertLessThan($fallback, $secondRule);
    }

    public function test_dialplan_service_can_preserve_a_dynamic_route_name(): void
    {
        $databasePath = sys_get_temp_dir() . '/fspbx-dynamic-route-name-' . bin2hex(random_bytes(8)) . '.sqlite';
        $originalConnection = config('database.default');
        touch($databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->uuid('dialplan_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->uuid('app_uuid')->nullable();
            $table->string('hostname')->nullable();
            $table->string('dialplan_name');
            $table->string('dialplan_number')->nullable();
            $table->string('dialplan_destination')->nullable();
            $table->string('dialplan_context');
            $table->string('dialplan_continue');
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order');
            $table->string('dialplan_enabled');
            $table->string('dialplan_description')->nullable();
            $table->dateTime('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
            $table->dateTime('update_date')->nullable();
            $table->uuid('update_user')->nullable();
        });

        Schema::create('v_dialplan_details', function (Blueprint $table) {
            $table->uuid('dialplan_detail_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->uuid('dialplan_uuid');
            $table->string('dialplan_detail_tag');
            $table->string('dialplan_detail_type')->nullable();
            $table->text('dialplan_detail_data')->nullable();
            $table->string('dialplan_detail_break')->nullable();
            $table->string('dialplan_detail_inline')->nullable();
            $table->integer('dialplan_detail_group');
            $table->integer('dialplan_detail_order');
            $table->string('dialplan_detail_enabled');
            $table->dateTime('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
        });

        try {
            $service = new class extends DialplanService
            {
                public function clearDialplanCache(?string $context): void
                {
                    // The name persistence test does not need filesystem cache access.
                }
            };

            $dialplan = $service->save([
                'editor_mode' => 'builder',
                'domain_uuid' => null,
                'dialplan_name' => 'Dynamic Route: Main DID map',
                'dialplan_number' => '9500',
                'dialplan_destination' => 'true',
                'dialplan_context' => 'account.example.com',
                'dialplan_continue' => 'false',
                'dialplan_order' => 235,
                'dialplan_enabled' => 'true',
                'dialplan_description' => null,
                'dialplan_details' => [[
                    'dialplan_detail_tag' => 'condition',
                    'dialplan_detail_type' => 'destination_number',
                    'dialplan_detail_data' => '^9500$',
                    'dialplan_detail_group' => 0,
                    'dialplan_detail_order' => 10,
                    'dialplan_detail_enabled' => 'true',
                ]],
            ], new Dialplans(['dialplan_uuid' => '28a501e9-3e73-46cc-a8bc-a5cb04f828a2']), preserveName: true);

            $this->assertSame('Dynamic Route: Main DID map', $dialplan->getRawOriginal('dialplan_name'));
            $this->assertStringStartsWith('<extension name="Dynamic Route: Main DID map"', $dialplan->dialplan_xml);
        } finally {
            DB::purge('sqlite');
            config(['database.default' => $originalConnection]);
            @unlink($databasePath);
        }
    }
}
