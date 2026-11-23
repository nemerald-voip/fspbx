<?php

namespace Tests\Unit;

use App\Events\ExtensionUpdated;
use App\Http\Controllers\ExtensionsController;
use App\Jobs\UpdateAppSettings;
use App\Models\FusionCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionProperty;
use Tests\TestCase;

class ExtensionPasswordRingotelSyncTest extends TestCase
{
    private string $domain = '11111111-1111-4111-8111-111111111111';
    private string $otherDomain = '22222222-2222-4222-8222-222222222222';
    private string $extension = '33333333-3333-4333-8333-333333333333';
    private string $otherExtension = '44444444-4444-4444-8444-444444444444';
    private string $cacheDirectory;
    private array $previousCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'activitylog.enabled' => false,
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.after_commit' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Event::fake([ExtensionUpdated::class]);
        Http::fake();

        foreach ([
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'password', 'user_context', 'effective_caller_id_name'],
            'extension_advanced_settings' => ['setting_uuid', 'extension_uuid', 'suspended'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id', 'voicemail_mail_to'],
            'v_device_lines' => ['device_line_uuid', 'domain_uuid', 'auth_id', 'password', 'external_line'],
            'mobile_app_users' => ['mobile_app_user_uuid', 'domain_uuid', 'extension_uuid', 'org_id', 'user_id', 'status'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) { $blueprint->string($column)->nullable(); }
                $blueprint->primary($columns[0]);
            });
        }
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        foreach ([$this->extension => $this->domain, $this->otherExtension => $this->otherDomain] as $uuid => $domain) {
            DB::table('v_extensions')->insert([
                'extension_uuid' => $uuid, 'domain_uuid' => $domain, 'extension' => '100',
                'password' => 'original-test-password', 'user_context' => 'tenant.example.test',
            ]);
            DB::table('v_device_lines')->insert([
                'device_line_uuid' => (string) Str::uuid(), 'domain_uuid' => $domain,
                'auth_id' => '100', 'password' => 'original-test-password',
            ]);
        }

        session(['domain_uuid' => $this->domain, 'permissions' => [
            (object) ['permission_name' => 'extension_password'],
            (object) ['permission_name' => 'extension_view'],
        ]]);

        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-password-sync-'.bin2hex(random_bytes(6));
        mkdir($this->cacheDirectory);
        foreach (['cacheType' => 'file', 'cacheLocation' => $this->cacheDirectory] as $property => $value) {
            $reflection = new ReflectionProperty(FusionCache::class, $property);
            $this->previousCache[$property] = $reflection->getValue();
            $reflection->setValue(null, $value);
        }
        file_put_contents($this->cacheFile(), 'test-directory-cache');
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel()) { DB::rollBack(); }
        DB::purge('sqlite');
        foreach ($this->previousCache as $property => $value) {
            (new ReflectionProperty(FusionCache::class, $property))->setValue(null, $value);
        }
        if (isset($this->cacheDirectory)) { File::deleteDirectory($this->cacheDirectory); }
        parent::tearDown();
    }

    public static function actions(): array
    {
        return [['update'], ['regenerate']];
    }

    /** @dataProvider actions */
    public function test_password_actions_queue_only_the_same_account_password_and_preserve_local_device_updates(string $action): void
    {
        $this->mobileApp(['status' => '-1']);
        $this->mobileApp(['domain_uuid' => $this->otherDomain, 'user_id' => 'foreign-user']);
        $this->mobileApp(['extension_uuid' => $this->otherExtension, 'user_id' => 'other-extension']);
        DB::table('v_device_lines')->insert([
            'device_line_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->domain,
            'auth_id' => '100', 'password' => 'external-test-password', 'external_line' => 't',
        ]);

        $response = $this->passwordAction($action);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $password = DB::table('v_extensions')->where('extension_uuid', $this->extension)->value('password');
        $this->assertNotSame('original-test-password', $password);
        if ($action === 'regenerate') {
            $this->assertSame($password, $response->getData(true)['data']['password']);
        } else {
            $this->assertSame('new-test-password', $password);
        }

        $jobs = $this->queuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertInstanceOf(UpdateAppSettings::class, $jobs[0]);
        $this->assertSame([
            'user_id' => 'ringotel-user', 'org_id' => 'ringotel-org',
            'password' => $password, 'no_email' => true,
        ], $jobs[0]->attributes);
        $this->assertSame('default', $jobs[0]->queue);
        $this->assertTrue($jobs[0]->afterCommit);
        $this->assertSame('-1', DB::table('mobile_app_users')->where('user_id', 'ringotel-user')->value('status'));
        $this->assertSame($password, DB::table('v_device_lines')->where('domain_uuid', $this->domain)->whereNull('external_line')->value('password'));
        $this->assertSame('original-test-password', DB::table('v_device_lines')->where('domain_uuid', $this->otherDomain)->value('password'));
        $this->assertSame('external-test-password', DB::table('v_device_lines')->where('external_line', 't')->value('password'));
        $this->assertFileDoesNotExist($this->cacheFile());
        Http::assertNothingSent();
    }

    /** @dataProvider actions */
    public function test_jobs_are_not_enqueued_until_the_outer_transaction_commits(string $action): void
    {
        $this->mobileApp();
        DB::beginTransaction();
        $response = $this->passwordAction($action);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertCount(1, $this->queuedJobs());
    }

    /** @dataProvider actions */
    public function test_rollback_discards_the_password_update_job(string $action): void
    {
        $this->mobileApp();
        DB::beginTransaction();
        $response = $this->passwordAction($action);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('original-test-password', DB::table('v_extensions')->where('extension_uuid', $this->extension)->value('password'));
    }

    /** @dataProvider actions */
    public function test_extensions_without_a_ringotel_mapping_do_not_queue_a_job(string $action): void
    {
        $response = $this->passwordAction($action);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @dataProvider actions */
    public function test_foreign_account_extensions_are_not_changed_or_queued(string $action): void
    {
        $this->mobileApp(['domain_uuid' => $this->otherDomain, 'extension_uuid' => $this->otherExtension]);
        $response = $this->passwordAction($action, $this->otherExtension);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('original-test-password', DB::table('v_extensions')->where('extension_uuid', $this->otherExtension)->value('password'));
    }

    public function test_duplicate_mappings_are_deduplicated_and_incomplete_mappings_are_skipped(): void
    {
        $this->mobileApp();
        $this->mobileApp();
        $this->mobileApp(['user_id' => 'second-user']);
        $this->mobileApp(['user_id' => null]);
        $this->mobileApp(['org_id' => '']);
        $response = $this->passwordAction('update');
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertEqualsCanonicalizing(['ringotel-user', 'second-user'], array_map(fn ($job) => $job->attributes['user_id'], $this->queuedJobs()));
    }

    public function test_invalid_password_input_does_not_change_or_queue_anything(): void
    {
        $this->mobileApp();
        $response = $this->passwordAction('update', null, '');
        $this->assertSame(500, $response->getStatusCode());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('original-test-password', DB::table('v_extensions')->where('extension_uuid', $this->extension)->value('password'));
    }

    private function mobileApp(array $overrides = []): void
    {
        DB::table('mobile_app_users')->insert($overrides + [
            'mobile_app_user_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->domain,
            'extension_uuid' => $this->extension, 'org_id' => 'ringotel-org', 'user_id' => 'ringotel-user', 'status' => '1',
        ]);
    }

    private function passwordAction(string $action, ?string $extension = null, string $password = 'new-test-password')
    {
        $request = Request::create('/api/extensions/password', 'POST', [
            'extension_uuid' => $extension ?? $this->extension, 'password' => $password,
        ]);
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);
        $controller = new ExtensionsController();

        return $action === 'regenerate'
            ? $controller->regenerateSipCredentials($extension ?? $this->extension)
            : $controller->updatePassword();
    }

    private function queuedJobs(): array
    {
        return DB::table('jobs')->orderBy('id')->pluck('payload')->map(function ($payload) {
            return unserialize(json_decode($payload, true)['data']['command']);
        })->all();
    }

    private function cacheFile(): string
    {
        return $this->cacheDirectory.'/directory.100@tenant.example.test';
    }
}
