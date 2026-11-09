<?php

namespace Tests\Unit;

use App\Jobs\SendNewVoicemailNotificationByEmail;
use App\Models\VoicemailMessages;
use App\Services\FreeswitchEslService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class VoicemailEmailCleanupMwiTest extends TestCase
{
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;
    private Manager $database;
    private $logger;
    private array $commands = [];
    private bool $failNotification = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();

        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstances();
        $this->database = new Manager($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->bootEloquent();
        Model::unsetEventDispatcher();
        $this->database->getConnection()->getSchemaBuilder()->create('v_voicemail_messages', function (Blueprint $table) {
            $table->uuid('voicemail_message_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('voicemail_uuid');
            $table->string('message_status')->nullable();
        });

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->andReturn(false);
        $filesystem = Mockery::mock();
        $filesystem->shouldReceive('disk')->with('voicemail')->andReturn($disk);
        $app->instance('filesystem', $filesystem);

        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->andReturnUsing(function ($command) {
            $this->commands[] = $command;
            if ($this->failNotification) {
                throw new RuntimeException('ESL unavailable');
            }
            return '+OK';
        });
        $app->instance(FreeswitchEslService::class, $esl);
        $this->logger = Mockery::spy();
        $app->instance('log', $this->logger);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            $this->database->getConnection()->disconnect();
            if ($this->previousResolver) {
                Model::setConnectionResolver($this->previousResolver);
            } else {
                Model::unsetConnectionResolver();
            }
            if ($this->previousDispatcher) {
                Model::setEventDispatcher($this->previousDispatcher);
            } else {
                Model::unsetEventDispatcher();
            }
            Container::setInstance($this->previousContainer);
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Facade::clearResolvedInstances();
            parent::tearDown();
        }
    }

    public function test_cleanup_counts_remaining_messages_only_for_the_same_mailbox_and_domain(): void
    {
        $deleted = $this->message();
        foreach ([null, '', 'new', 'saved', 'saved', 'deleted'] as $status) {
            $this->message($status);
        }
        foreach ([null, 'saved'] as $status) {
            $this->message($status, 'domain-b');
            $this->message($status, 'domain-a', 'other-mailbox');
        }

        $this->cleanup($deleted);
        $this->assertNotification($deleted, 3, 2);

        $this->assertSame(10, VoicemailMessages::count());
        $this->assertSame(2, VoicemailMessages::where('domain_uuid', 'domain-b')->count());
        $this->assertSame(2, VoicemailMessages::where('voicemail_uuid', 'other-mailbox')->count());
    }

    public function test_cleanup_reports_saved_messages_after_the_last_unread_message_is_deleted(): void
    {
        $deleted = $this->message();
        $this->message('saved');

        $this->cleanup($deleted);
        $this->assertNotification($deleted, 0, 1);

        $this->assertSame(1, VoicemailMessages::count());
    }

    public function test_cleanup_clears_mwi_when_the_mailbox_is_empty(): void
    {
        $deleted = $this->message();

        $this->cleanup($deleted);
        $this->assertNotification($deleted, 0, 0);

        $this->assertSame(0, VoicemailMessages::count());
    }

    public function test_notification_failure_does_not_fail_completed_cleanup(): void
    {
        $deleted = $this->message();
        $this->message();
        $this->failNotification = true;

        $this->cleanup($deleted);

        $this->logger->shouldHaveReceived('warning')->once()->with(
            'MWI update failed after voicemail delete',
            ['v' => '100', 'd' => 'example.test', 'e' => 'ESL unavailable'],
        );

        $this->assertSame(['bgapi luarun app/voicemail/resources/scripts/mwi_notify.lua 100 example.test 1 0'], $this->commands);
        $this->assertFalse(VoicemailMessages::whereKey($deleted->getKey())->exists());
        $this->assertSame(1, VoicemailMessages::count());
    }

    private function message(?string $status = null, string $domain = 'domain-a', string $mailbox = 'mailbox-a'): VoicemailMessages
    {
        $uuid = (string) Str::uuid();
        $this->database->getConnection()->table('v_voicemail_messages')->insert([
            'voicemail_message_uuid' => $uuid,
            'domain_uuid' => $domain,
            'voicemail_uuid' => $mailbox,
            'message_status' => $status,
        ]);

        return VoicemailMessages::findOrFail($uuid)
            ->setRelation('domain', (object) ['domain_name' => 'example.test'])
            ->setRelation('voicemail', (object) ['voicemail_id' => '100']);
    }

    private function assertNotification(VoicemailMessages $deleted, int $new, int $saved): void
    {
        $this->assertFalse(VoicemailMessages::whereKey($deleted->getKey())->exists());
        $this->assertSame(["bgapi luarun app/voicemail/resources/scripts/mwi_notify.lua 100 example.test {$new} {$saved}"], $this->commands);
        $this->logger->shouldNotHaveReceived('warning');
    }

    private function cleanup(VoicemailMessages $message): void
    {
        (new ReflectionMethod(SendNewVoicemailNotificationByEmail::class, 'deleteVoicemailSilently'))
            ->invoke(new SendNewVoicemailNotificationByEmail([]), $message);
    }
}
