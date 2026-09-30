<?php

namespace Tests\Unit;

use App\Services\CdrDataService;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\{DB, Facade, Schema};
use PHPUnit\Framework\TestCase;

class CdrCallbackVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $db = new Manager($app);
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $manager = $db->getDatabaseManager();
        $app->instance('db', $manager);
        $app->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
        Schema::create('v_xml_cdr', function (Blueprint $table) {
            $table->string('xml_cdr_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('cc_member_session_uuid')->nullable();
            $table->string('originating_leg_uuid')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_both_callback_roles_are_visible_without_exposing_other_tenants_or_ordinary_secondary_legs(): void
    {
        Schema::table('v_xml_cdr', fn (Blueprint $table) => $table->uuid('cc_callback_attempt_uuid')->nullable());
        foreach ([
            ['normal', 'account-a', null, null, null],
            ['ordinary-agent', 'account-a', 'member', null, null],
            ['ordinary-child', 'account-a', null, 'parent', null],
            ['callback-agent', 'account-a', 'member', null, 'attempt'],
            ['callback-customer', 'account-a', null, 'parent', 'attempt'],
            ['other-account-callback', 'account-b', 'member', 'parent', 'attempt'],
        ] as $row) {
            DB::table('v_xml_cdr')->insert(array_combine([
                'xml_cdr_uuid', 'domain_uuid', 'cc_member_session_uuid', 'originating_leg_uuid', 'cc_callback_attempt_uuid',
            ], $row));
        }

        $this->assertSame(['callback-agent', 'callback-customer', 'normal'], $this->visibleCalls());
        $this->assertSame('parent', DB::table('v_xml_cdr')->where('xml_cdr_uuid', 'callback-customer')->value('originating_leg_uuid'));
    }

    public function test_ordinary_cdr_queries_work_before_callback_schema_is_installed(): void
    {
        DB::table('v_xml_cdr')->insert([
            ['xml_cdr_uuid' => 'normal', 'domain_uuid' => 'account-a', 'originating_leg_uuid' => null],
            ['xml_cdr_uuid' => 'child', 'domain_uuid' => 'account-a', 'originating_leg_uuid' => 'parent'],
        ]);
        $this->assertSame(['normal'], $this->visibleCalls());
    }

    private function visibleCalls(): array
    {
        $service = new class extends CdrDataService {
            public function visibleCalls(): array
            {
                return DB::table('v_xml_cdr')->where('domain_uuid', 'account-a')
                    ->where(fn ($query) => $this->filterCallLegs($query))
                    ->orderBy('xml_cdr_uuid')->pluck('xml_cdr_uuid')->all();
            }
        };

        return $service->visibleCalls();
    }
}
