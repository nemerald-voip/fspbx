<?php

namespace Tests\Unit;

use App\Models\DomainSettings;
use App\Services\YealinkRpsCloudProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YealinkRpsCloudProviderTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-yealink-rps-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'services.ztp.yealink.api_url' => 'https://yealink-rps.test',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });

        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });

        $this->provider()->setCredentials([
            'access_key_id' => 'access-key-id',
            'access_key_secret' => 'access-key-secret',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_it_registers_a_device_to_the_accounts_rps_server(): void
    {
        $this->pairServer();

        Http::fake([
            'https://yealink-rps.test/api/open/v1/device/add' => Http::response([
                'ret' => 1,
                'data' => [['id' => 'device-id', 'mac' => '001565123123']],
                'error' => null,
            ]),
        ]);

        $result = $this->provider()->createDevice([
            'domain_uuid' => 'domain-uuid',
            'device_address' => '00:15:65:12:31:23',
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(fn ($request) => $request->url() === 'https://yealink-rps.test/api/open/v1/device/add'
            && $request['macs'] === ['001565123123']
            && $request['serverId'] === 'server-id'
            && $request->hasHeader('X-Ca-Key', 'access-key-id')
            && $request->hasHeader('Content-MD5')
            && $request->hasHeader('X-Ca-Signature'));
    }

    public function test_it_normalizes_rps_device_paging_for_the_shared_sync_flow(): void
    {
        Http::fake([
            'https://yealink-rps.test/api/open/v1/device/list' => Http::response([
                'ret' => 1,
                'data' => [
                    'skip' => 0,
                    'limit' => 1,
                    'total' => 2,
                    'data' => [['id' => 'device-id', 'mac' => '001565123123']],
                ],
                'error' => null,
            ]),
        ]);

        $result = $this->provider()->getDevices(1, null);

        $this->assertTrue($result['success']);
        $this->assertSame('001565123123', $result['data']['results'][0]['mac']);
        $this->assertSame('1', $result['data']['next']);
    }

    public function test_it_treats_a_semantic_rps_error_as_a_failed_request(): void
    {
        $this->pairServer();
        Log::spy();

        Http::fake([
            'https://yealink-rps.test/api/open/v1/device/add' => Http::response([
                'ret' => -1,
                'data' => '001565123123',
                'error' => [
                    'msg' => 'device.mac.added.by.other',
                    'errorCode' => 409,
                    'fieldErrors' => [],
                ],
            ], 200),
        ]);

        $result = $this->provider()->createDevice([
            'domain_uuid' => 'domain-uuid',
            'device_address' => '001565123123',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('device.mac.added.by.other', $result['error']);
    }

    public function test_it_removes_bound_devices_before_deleting_an_rps_server(): void
    {
        Http::fake([
            'https://yealink-rps.test/api/open/v1/device/list' => Http::response([
                'ret' => 1,
                'data' => [
                    'skip' => 0,
                    'limit' => 100,
                    'total' => 2,
                    'data' => [
                        ['id' => 'bound-device', 'serverId' => 'server-id'],
                        ['id' => 'other-device', 'serverId' => 'other-server'],
                    ],
                ],
                'error' => null,
            ]),
            'https://yealink-rps.test/api/open/v1/device/delete' => Http::response([
                'ret' => 0,
                'data' => null,
                'error' => null,
            ]),
            'https://yealink-rps.test/api/open/v1/server/delete' => Http::response([
                'ret' => 0,
                'data' => null,
                'error' => null,
            ]),
        ]);

        $this->assertTrue($this->provider()->deleteOrganization('server-id'));

        Http::assertSent(fn ($request) => $request->url() === 'https://yealink-rps.test/api/open/v1/device/delete'
            && $request['ids'] === ['bound-device']);
        Http::assertSent(fn ($request) => $request->url() === 'https://yealink-rps.test/api/open/v1/server/delete'
            && $request['ids'] === ['server-id']);
    }

    private function provider(): YealinkRpsCloudProvider
    {
        return new YealinkRpsCloudProvider();
    }

    private function pairServer(): void
    {
        DomainSettings::create([
            'domain_uuid' => 'domain-uuid',
            'domain_setting_category' => 'cloud provision',
            'domain_setting_subcategory' => 'yealink_rps_server_id',
            'domain_setting_name' => 'text',
            'domain_setting_value' => 'server-id',
            'domain_setting_enabled' => 'true',
        ]);
    }
}
