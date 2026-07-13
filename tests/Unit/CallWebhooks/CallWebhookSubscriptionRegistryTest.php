<?php

namespace Tests\Unit\CallWebhooks;

use App\Services\CallWebhooks\CallWebhookSubscriptionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallWebhookSubscriptionRegistryTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-call-webhooks-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'cache.stores.redis' => ['driver' => 'array'],
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app('cache')->forgetDriver('redis');

        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name');
        });

        Schema::create('call_webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('call_webhook_uuid')->primary();
            $table->uuid('domain_uuid')->unique();
            $table->text('endpoint_url');
            $table->text('signing_secret');
            $table->boolean('enabled');
            $table->json('events');
            $table->timestamps();
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

    public function test_it_caches_enabled_subscriptions_and_refreshes_after_invalidation(): void
    {
        $firstDomainUuid = $this->insertSubscription('first.example.com');
        $registry = app(CallWebhookSubscriptionRegistry::class);

        DB::enableQueryLog();

        $this->assertTrue($registry->hasAny());
        $this->assertSame($firstDomainUuid, $registry->domainUuidForName('FIRST.EXAMPLE.COM'));
        $queryCountAfterInitialLoad = count(DB::getQueryLog());

        $this->assertNotNull($registry->forDomainUuid($firstDomainUuid));
        $this->assertCount($queryCountAfterInitialLoad, DB::getQueryLog());

        $secondDomainUuid = $this->insertSubscription('second.example.com');
        $this->assertNull($registry->forDomainUuid($secondDomainUuid));

        $registry->invalidate();

        $this->assertNotNull($registry->forDomainUuid($secondDomainUuid));
        $this->assertGreaterThan($queryCountAfterInitialLoad, count(DB::getQueryLog()));
    }

    public function test_it_negative_caches_when_no_accounts_have_webhooks_enabled(): void
    {
        $registry = app(CallWebhookSubscriptionRegistry::class);
        DB::enableQueryLog();

        $this->assertFalse($registry->hasAny());
        $queryCountAfterInitialLoad = count(DB::getQueryLog());

        $this->assertFalse($registry->hasAny());
        $this->assertCount($queryCountAfterInitialLoad, DB::getQueryLog());
    }

    private function insertSubscription(string $domainName): string
    {
        $domainUuid = (string) Str::uuid();

        DB::table('v_domains')->insert([
            'domain_uuid' => $domainUuid,
            'domain_name' => $domainName,
        ]);

        DB::table('call_webhook_subscriptions')->insert([
            'call_webhook_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'endpoint_url' => 'https://crm.example.com/webhooks',
            'signing_secret' => 'encrypted-placeholder',
            'enabled' => true,
            'events' => json_encode(['call.ringing']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $domainUuid;
    }
}
