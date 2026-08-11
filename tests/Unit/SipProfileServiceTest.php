<?php

namespace Tests\Unit;

use App\Services\SipProfileService;
use App\Services\SofiaProfileRuntimeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SipProfileServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-sip-profile-service-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_sip_profile_domains', function (Blueprint $table) {
            $table->string('sip_profile_domain_uuid')->primary();
            $table->string('sip_profile_uuid');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_empty_kept_list_deletes_children_without_an_empty_uuid_binding(): void
    {
        $profileUuid = '8423c996-d4d3-4420-88a5-fd8d87d5cde0';

        DB::table('v_sip_profile_domains')->insert([
            'sip_profile_domain_uuid' => '9ce8d365-88cc-4c09-8d12-74ac3a4a78d7',
            'sip_profile_uuid' => $profileUuid,
        ]);

        DB::enableQueryLog();

        $service = new SipProfileService($this->createMock(SofiaProfileRuntimeService::class));
        $method = new \ReflectionMethod($service, 'deleteMissingChildren');
        $method->invoke(
            $service,
            'v_sip_profile_domains',
            'sip_profile_domain_uuid',
            $profileUuid,
            []
        );

        $deleteQuery = collect(DB::getQueryLog())->last();

        $this->assertSame([$profileUuid], $deleteQuery['bindings']);
        $this->assertDatabaseCount('v_sip_profile_domains', 0);
    }
}
