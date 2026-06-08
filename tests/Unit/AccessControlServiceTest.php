<?php

namespace Tests\Unit;

use App\Models\AccessControl;
use App\Models\Gateways;
use App\Services\AccessControlService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-access-control-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_saving_providers_list_does_not_rewrite_managed_gateway_nodes(): void
    {
        $gatewayUuid = (string) Str::uuid();
        $description = 'Managed gateway:' . strtolower($gatewayUuid);

        $providers = AccessControl::query()->create([
            'access_control_name' => AccessControlService::PROVIDERS_LIST,
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IP access control list.',
        ]);

        $service = new AccessControlService();

        $service->saveAccessControl($providers, [
            'access_control_name' => AccessControlService::PROVIDERS_LIST,
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IP access control list.',
            'nodes' => [
                [
                    'node_type' => 'allow',
                    'node_cidr' => '192.76.120.10/32',
                    'node_description' => $description,
                ],
                [
                    'node_type' => 'allow',
                    'node_cidr' => '64.16.250.10/32',
                    'node_description' => $description,
                ],
                [
                    'node_type' => 'allow',
                    'node_cidr' => '1.1.1.1',
                    'node_description' => 'Test',
                ],
            ],
        ]);

        $nodes = $providers->refresh()->nodes()
            ->get(['node_cidr', 'node_description'])
            ->map(fn ($node) => $node->only(['node_cidr', 'node_description']))
            ->values()
            ->all();

        $this->assertSame([
            [
                'node_cidr' => '1.1.1.1/32',
                'node_description' => 'Test',
            ],
            [
                'node_cidr' => '192.76.120.10/32',
                'node_description' => $description,
            ],
            [
                'node_cidr' => '64.16.250.10/32',
                'node_description' => $description,
            ],
        ], $nodes);
    }

    public function test_gateway_sync_preserves_manual_provider_nodes_and_replaces_only_its_own_nodes(): void
    {
        $service = new AccessControlService();
        $gateway = new Gateways([
            'gateway_uuid' => (string) Str::uuid(),
            'gateway' => 'carrier-a',
        ]);
        $gatewayDescription = 'Managed gateway: carrier-a (' . strtolower((string) $gateway->gateway_uuid) . ')';
        $otherGatewayUuid = (string) Str::uuid();

        $providers = AccessControl::query()->create([
            'access_control_name' => AccessControlService::PROVIDERS_LIST,
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IP access control list.',
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '1.1.1.1/32',
            'node_description' => 'Test',
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '192.76.120.10/32',
            'node_description' => 'Managed gateway:' . strtolower((string) $gateway->gateway_uuid),
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '64.16.250.10/32',
            'node_description' => 'Managed gateway:' . strtolower($otherGatewayUuid),
        ]);

        $oldGatewayList = AccessControl::query()->create([
            'access_control_name' => $service->gatewayListName($gateway),
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IPs for carrier-a',
        ]);

        $oldGatewayList->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '203.0.113.10/32',
            'node_description' => 'Managed gateway:' . strtolower((string) $gateway->gateway_uuid),
        ]);

        $service->syncGatewayProviderIps($gateway, [
            ['node_cidr' => '198.51.100.25'],
        ]);

        $nodes = $providers->refresh()->nodes()
            ->get(['node_cidr', 'node_description'])
            ->map(fn ($node) => $node->only(['node_cidr', 'node_description']))
            ->values()
            ->all();

        $this->assertSame([
            [
                'node_cidr' => '1.1.1.1/32',
                'node_description' => 'Test',
            ],
            [
                'node_cidr' => '198.51.100.25/32',
                'node_description' => $gatewayDescription,
            ],
            [
                'node_cidr' => '64.16.250.10/32',
                'node_description' => 'Managed gateway:' . strtolower($otherGatewayUuid),
            ],
        ], $nodes);

        $this->assertFalse(
            AccessControl::query()
                ->where('access_control_name', $service->gatewayListName($gateway))
                ->exists()
        );
    }

    public function test_removing_generated_gateway_list_by_name_preserves_providers_nodes(): void
    {
        $service = new AccessControlService();
        $gateway = new Gateways([
            'gateway_uuid' => (string) Str::uuid(),
            'gateway' => 'carrier-a',
        ]);
        $description = 'Managed gateway:' . strtolower((string) $gateway->gateway_uuid);

        $providers = AccessControl::query()->create([
            'access_control_name' => AccessControlService::PROVIDERS_LIST,
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IP access control list.',
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '1.1.1.1/32',
            'node_description' => 'Test',
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '192.76.120.10/32',
            'node_description' => $description,
        ]);

        $generatedList = AccessControl::query()->create([
            'access_control_name' => $service->gatewayListName($gateway),
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IPs for carrier-a',
        ]);

        $generatedList->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '192.76.120.10/32',
            'node_description' => $description,
        ]);

        $service->removeGatewayProviderIpsForListName($service->gatewayListName($gateway));

        $this->assertSame([
            '1.1.1.1/32',
            '192.76.120.10/32',
        ], $providers->refresh()->nodes()->pluck('node_cidr')->values()->all());

        $this->assertSame(0, $generatedList->refresh()->nodes()->count());
    }

    public function test_preserving_generated_gateway_list_copies_missing_provider_rows_before_delete(): void
    {
        $service = new AccessControlService();
        $gateway = new Gateways([
            'gateway_uuid' => (string) Str::uuid(),
            'gateway' => 'carrier-a',
        ]);
        $description = 'Managed gateway:' . strtolower((string) $gateway->gateway_uuid);

        $generatedList = AccessControl::query()->create([
            'access_control_name' => $service->gatewayListName($gateway),
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IPs for carrier-a',
        ]);

        $generatedList->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '192.76.120.10/32',
            'node_description' => $description,
        ]);

        $service->preserveProviderIpsForList($generatedList->refresh()->load('nodes'));
        $generatedList->nodes()->delete();
        $generatedList->delete();

        $providers = AccessControl::query()
            ->where('access_control_name', AccessControlService::PROVIDERS_LIST)
            ->firstOrFail();

        $this->assertSame(['192.76.120.10/32'], $providers->nodes()->pluck('node_cidr')->values()->all());
        $this->assertSame(['192.76.120.10/32'], $service->gatewayCidrs($gateway)->all());
    }

    public function test_preserving_generated_gateway_list_does_not_restore_stale_rows_when_providers_has_gateway_rows(): void
    {
        $service = new AccessControlService();
        $gateway = new Gateways([
            'gateway_uuid' => (string) Str::uuid(),
            'gateway' => 'carrier-a',
        ]);
        $description = 'Managed gateway:' . strtolower((string) $gateway->gateway_uuid);

        $providers = AccessControl::query()->create([
            'access_control_name' => AccessControlService::PROVIDERS_LIST,
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IP access control list.',
        ]);

        $providers->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '198.51.100.25/32',
            'node_description' => $description,
        ]);

        $generatedList = AccessControl::query()->create([
            'access_control_name' => $service->gatewayListName($gateway),
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IPs for carrier-a',
        ]);

        $generatedList->nodes()->create([
            'node_type' => 'allow',
            'node_cidr' => '192.76.120.10/32',
            'node_description' => $description,
        ]);

        $service->preserveProviderIpsForList($generatedList->refresh()->load('nodes'));
        $generatedList->nodes()->delete();
        $generatedList->delete();

        $this->assertSame(['198.51.100.25/32'], $providers->refresh()->nodes()->pluck('node_cidr')->values()->all());
        $this->assertSame(['198.51.100.25/32'], $service->gatewayCidrs($gateway)->all());
    }

    private function createSchema(): void
    {
        Schema::create('v_access_controls', function (Blueprint $table) {
            $table->string('access_control_uuid')->primary();
            $table->string('access_control_name')->nullable();
            $table->string('access_control_default')->nullable();
            $table->text('access_control_description')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->string('insert_user')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->string('update_user')->nullable();
        });

        Schema::create('v_access_control_nodes', function (Blueprint $table) {
            $table->string('access_control_node_uuid')->primary();
            $table->string('access_control_uuid')->nullable();
            $table->string('node_type')->nullable();
            $table->string('node_cidr')->nullable();
            $table->text('node_description')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->string('insert_user')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->string('update_user')->nullable();
        });
    }
}
