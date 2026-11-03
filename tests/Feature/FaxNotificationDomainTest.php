<?php

namespace Tests\Feature;

use App\Jobs\SendFaxFailedNotification;
use App\Mail\FaxFailed;
use App\Models\Domain;
use App\Models\Faxes;
use App\Services\EmailTemplateService;
use App\Services\FaxSendService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FaxNotificationDomainTest extends TestCase
{
    private string $directory;
    private string $originalConnection;
    private Faxes $fax;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fax-notification-domain-'.Str::uuid();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'fax_notification_domain_test',
            'database.connections.fax_notification_domain_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'cache.default' => 'array',
            'logging.default' => 'null',
            'slack.fax' => null,
            'filesystems.disks.fax' => ['driver' => 'local', 'root' => $this->directory],
        ]);
        DB::purge('fax_notification_domain_test');
        Storage::forgetDisk('fax');
        Bus::fake();
        Mail::fake();
        $this->mock(EmailTemplateService::class)->shouldReceive('render')->andReturn(['available' => false]);

        Schema::create('v_fax', function (Blueprint $table) {
            $table->uuid('fax_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('fax_extension');
        });
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_value')->nullable();
        });

        $this->fax = Faxes::create(['domain_uuid' => (string) Str::uuid(), 'fax_extension' => '50000']);
        $this->fax->setRelation('domain', new Domain(['domain_name' => 'fax.example.test']));
        session()->put('domain_uuid', (string) Str::uuid());

        $limiter = Mockery::mock();
        Redis::shouldReceive('throttle')->with('fax')->andReturn($limiter);
        $limiter->shouldReceive('allow')->with(2)->andReturnSelf();
        $limiter->shouldReceive('every')->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->andReturnUsing(fn ($callback) => $callback());
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('fax');
        File::deleteDirectory($this->directory);
        DB::purge('fax_notification_domain_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_missing_attachment_notification_uses_the_authorized_fax_account(): void
    {
        $result = FaxSendService::send([
            'fax_uuid' => $this->fax->fax_uuid,
            'domain_uuid' => (string) Str::uuid(),
            'from' => 'sender@example.test', 'fax_destination' => '+12025550123',
            'attachments' => [],
        ]);

        $this->assertSame('No attachments', $result);
        $this->assertQueuedNotificationDomain($this->fax->domain_uuid);
    }

    public function test_conversion_failure_keeps_the_actual_fax_account_and_sender(): void
    {
        // An empty conversion result exercises the failure path without running converters.
        $result = (new FaxSendService())->convertAttachmentsToTif([], [
            'domain_uuid' => (string) Str::uuid(),
            'from' => 'sender@example.test', 'fax_destination' => '+12025550123',
        ], $this->fax);

        $this->assertFalse($result['success']);
        $this->assertQueuedNotificationDomain($this->fax->domain_uuid);
    }

    public function test_unresolved_fax_does_not_inherit_an_unrelated_session_or_payload_account(): void
    {
        FaxSendService::send([
            'domain_uuid' => $this->fax->domain_uuid,
            'from' => 'sender@example.test', 'fax_destination' => '+12025550123',
            'attachments' => [],
        ]);

        $this->assertQueuedNotificationDomain(null);
    }

    private function assertQueuedNotificationDomain(?string $domainUuid): void
    {
        Bus::assertDispatchedTimes(SendFaxFailedNotification::class, 1);
        $job = Bus::dispatched(SendFaxFailedNotification::class)->sole();
        $job->handle();

        Mail::assertSent(FaxFailed::class, function ($mail) use ($domainUuid) {
            return $mail->hasTo('sender@example.test')
                && $mail->attributes['domain_uuid'] === $domainUuid;
        });
    }
}
