<?php

namespace Tests\Unit;

use App\Jobs\SendNewVoicemailNotificationByEmail;
use App\Mail\VoicemailNotification;
use App\Models\Extensions;
use App\Models\VoicemailMessages;
use App\Models\Voicemails;
use App\Observers\VoicemailObserver;
use App\Services\EmailTemplateService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class VoicemailDestinationSafetyTest extends TestCase
{
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;
    private Manager $database;
    private $logger;
    private array $checkedPaths = [];
    private array $existingPaths = [];
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        Model::clearBootedModels();

        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstances();
        $app->instance('config', new Repository(['mail' => ['default' => 'array']]));
        $app->instance('cache', new CacheRepository(new ArrayStore()));
        $this->database = new Manager($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setEventDispatcher(new Dispatcher($app));
        $this->database->bootEloquent();
        Voicemails::observe(VoicemailObserver::class);
        $this->createSchema();

        $redis = Mockery::mock();
        $redis->shouldReceive('throttle')->with('email')->andReturnSelf();
        $redis->shouldReceive('allow')->with(2)->andReturnSelf();
        $redis->shouldReceive('every')->with(1)->andReturnSelf();
        $redis->shouldReceive('then')->andReturnUsing(fn ($callback) => $callback());
        $app->instance('redis', $redis);
        $this->logger = Mockery::spy();
        $app->instance('log', $this->logger);

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->andReturnUsing(function ($path) {
            $this->checkedPaths[] = $path;
            return in_array($path, $this->existingPaths, true);
        });
        $filesystem = Mockery::mock();
        $filesystem->shouldReceive('disk')->with('voicemail')->andReturn($disk);
        $app->instance('filesystem', $filesystem);
        $mail = Mockery::mock();
        $mail->shouldReceive('purge')->with('array');
        $mail->shouldReceive('to')->with('recipient@example.test')->andReturnSelf();
        $mail->shouldReceive('send')->andReturnUsing(function (VoicemailNotification $notification) {
            $this->sent[] = $notification;
        });
        $app->instance('mail.manager', $mail);
        $templates = Mockery::mock(EmailTemplateService::class);
        $templates->shouldReceive('render')->andReturn(['available' => false]);
        $app->instance(EmailTemplateService::class, $templates);

        $db = $this->database->getConnection();
        $db->table('v_domains')->insert(['domain_uuid' => 'account-a', 'domain_name' => 'example.test']);
        $db->table('v_voicemails')->insert([
            'voicemail_uuid' => 'mailbox-a', 'domain_uuid' => 'account-a', 'voicemail_id' => '100',
            'voicemail_mail_to' => 'recipient@example.test', 'voicemail_file' => 'attach',
            'voicemail_local_after_email' => 'true',
        ]);
        $db->table('v_voicemail_messages')->insert([
            'voicemail_message_uuid' => 'message-a', 'domain_uuid' => 'account-a',
            'voicemail_uuid' => 'mailbox-a', 'created_epoch' => 1700000000, 'message_length' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            $this->database->getConnection()->disconnect();
            Model::clearBootedModels();
            $this->previousResolver ? Model::setConnectionResolver($this->previousResolver) : Model::unsetConnectionResolver();
            $this->previousDispatcher ? Model::setEventDispatcher($this->previousDispatcher) : Model::unsetEventDispatcher();
            Container::setInstance($this->previousContainer);
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Facade::clearResolvedInstances();
            parent::tearDown();
        }
    }

    /** @dataProvider invalidRelationships */
    public function test_invalid_relationship_stops_before_file_lookup_and_preserves_message(string $case): void
    {
        $db = $this->database->getConnection();
        if ($case === 'missing mailbox') {
            $db->table('v_voicemails')->delete();
        } elseif ($case === 'wrong account') {
            $db->table('v_voicemails')->update(['domain_uuid' => 'account-b']);
        } else {
            $db->table('v_domains')->delete();
        }

        $this->runJob();

        $this->assertSame([], $this->checkedPaths);
        $this->assertSame([], $this->sent);
        $this->assertSame(1, VoicemailMessages::count());
        $this->logger->shouldHaveReceived('warning')->once()->with(
            'Skipping voicemail email: mailbox or account is missing or mismatched',
            ['voicemail_message_uuid' => 'message-a', 'domain_uuid' => 'account-a', 'voicemail_uuid' => 'mailbox-a'],
        );
        $this->logger->shouldNotHaveReceived('error');
    }

    public static function invalidRelationships(): array
    {
        return [['missing mailbox'], ['wrong account'], ['missing account']];
    }

    public function test_deleted_message_remains_a_silent_no_op(): void
    {
        $this->database->getConnection()->table('v_voicemail_messages')->delete();
        $this->runJob();
        $this->assertSame([], $this->checkedPaths);
        $this->assertSame([], $this->sent);
        $this->logger->shouldNotHaveReceived('warning');
        $this->logger->shouldNotHaveReceived('error');
    }

    /** @dataProvider recordings */
    public function test_valid_mailbox_preserves_notification_and_attachment_behavior(?string $extension): void
    {
        $path = $extension ? 'example.test/100/msg_message-a.'.$extension : null;
        $this->existingPaths = $path ? [$path] : [];
        $this->database->getConnection()->table('v_voicemail_messages')->insert([
            'voicemail_message_uuid' => 'foreign-message', 'domain_uuid' => 'account-b', 'voicemail_uuid' => 'mailbox-a',
        ]);

        $this->runJob();
        $this->runJob(); // A retry must retain the existing sent-message guard.

        $this->assertCount(1, $this->sent);
        $this->assertSame($path, $this->sent[0]->attributes['attachment_path']);
        $this->assertSame('100', $this->sent[0]->attributes['voicemail_id']);
        $this->assertSame(1, $this->sent[0]->attributes['new_messages']);
        $this->assertSame(2, VoicemailMessages::count());
        $this->logger->shouldNotHaveReceived('warning');
        if ($extension === null) {
            $this->logger->shouldHaveReceived('error')->twice()->with('Voicemail audio file not found for message: message-a');
        } else {
            $this->logger->shouldNotHaveReceived('error');
        }
    }

    public static function recordings(): array
    {
        return [['wav'], ['mp3'], [null]];
    }

    /** @dataProvider deletionPaths */
    public function test_deletion_cleans_both_directions_only_in_the_mailbox_account(bool $throughExtension): void
    {
        $db = $this->database->getConnection();
        $db->table('v_voicemails')->insert(['voicemail_uuid' => 'mailbox-b', 'domain_uuid' => 'account-b', 'voicemail_id' => '100']);
        foreach ([
            ['incoming', 'account-a', 'source', 'mailbox-a'],
            ['outgoing', 'account-a', 'mailbox-a', 'target'],
            ['self', 'account-a', 'mailbox-a', 'mailbox-a'],
            ['unrelated', 'account-a', 'source', 'target'],
            ['foreign-incoming', 'account-b', 'source', 'mailbox-a'],
            ['foreign-outgoing', 'account-b', 'mailbox-a', 'target'],
            ['foreign-valid', 'account-b', 'source', 'mailbox-b'],
        ] as [$id, $domain, $source, $target]) {
            $db->table('v_voicemail_destinations')->insert([
                'voicemail_destination_uuid' => $id, 'domain_uuid' => $domain,
                'voicemail_uuid' => $source, 'voicemail_uuid_copy' => $target,
            ]);
        }
        $db->beginTransaction();
        if ($throughExtension) {
            $db->table('v_extensions')->insert(['extension_uuid' => 'extension-a', 'domain_uuid' => 'account-a', 'extension' => '100']);
            $extension = Extensions::without('advSettings')
                ->with(['voicemail' => fn ($query) => $query->where('domain_uuid', 'account-a')])
                ->findOrFail('extension-a');
            $extension->voicemail->delete();
        } else {
            Voicemails::findOrFail('mailbox-a')->delete();
        }
        $this->assertFalse(Voicemails::whereKey('mailbox-a')->exists());
        $this->assertTrue(Voicemails::whereKey('mailbox-b')->exists());
        $this->assertSame(['foreign-incoming', 'foreign-outgoing', 'foreign-valid', 'unrelated'],
            $db->table('v_voicemail_destinations')->orderBy('voicemail_destination_uuid')->pluck('voicemail_destination_uuid')->all());
        $db->rollBack();
        $this->assertTrue(Voicemails::whereKey('mailbox-a')->exists());
        $this->assertSame(7, $db->table('v_voicemail_destinations')->count());
    }

    public static function deletionPaths(): array
    {
        return [[false], [true]];
    }

    private function runJob(): void
    {
        $job = new SendNewVoicemailNotificationByEmail(['message_uuid' => 'message-a']);
        $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldNotReceive('release', 'fail', 'delete');
        $job->setJob($queueJob);
        $job->handle();
    }

    private function createSchema(): void
    {
        $schema = $this->database->getConnection()->getSchemaBuilder();
        $definitions = [
            'v_voicemail_messages' => ['voicemail_message_uuid', 'domain_uuid', 'voicemail_uuid', 'created_epoch', 'caller_id_name', 'caller_id_number', 'message_length', 'message_transcription', 'message_status'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id', 'voicemail_mail_to', 'voicemail_transcription_enabled', 'voicemail_local_after_email', 'voicemail_description', 'voicemail_file'],
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name'],
            'v_voicemail_destinations' => ['voicemail_destination_uuid', 'domain_uuid', 'voicemail_uuid', 'voicemail_uuid_copy'],
        ];
        foreach (['default', 'domain'] as $type) {
            $definitions['v_'.$type.'_settings'] = array_map(fn ($column) => $type.'_setting_'.$column,
                ['uuid', 'category', 'subcategory', 'name', 'value', 'order', 'enabled']);
            if ($type === 'domain') $definitions['v_domain_settings'][] = 'domain_uuid';
        }
        foreach ($definitions as $name => $columns) {
            $schema->create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $index => $column) {
                    $field = $table->string($column);
                    $index === 0 ? $field->primary() : $field->nullable();
                }
            });
        }
    }
}
