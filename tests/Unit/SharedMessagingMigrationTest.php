<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class SharedMessagingMigrationTest extends TestCase
{
    private const TABLES = [
        'ringotel_conversations' => 'ringotel_conversation_uuid',
        'ringotel_message_syncs' => 'message_uuid',
        'ringotel_message_deliveries' => 'ringotel_message_delivery_uuid',
        'sms_destination_members' => 'sms_destination_member_uuid',
        'message_user_reads' => 'message_user_read_uuid',
        'message_user_hides' => 'message_user_hide_uuid',
        'message_groups' => 'message_group_uuid',
    ];

    private Container $previousContainer;
    private $previousFacadeApplication;
    private ?string $schema = null;
    private $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('FSPBX_MESSAGING_MIGRATION_PG_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Run bash tests/Unit/run-shared-messaging-migration-lab.sh to test the migration on PostgreSQL.');
        }
        // The runner creates a private cluster; never use application credentials.
        if (! str_starts_with($socket, '/tmp/fspbx-messaging-migration.')) {
            throw new \RuntimeException('Use the disposable messaging migration runner.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository());
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstances();

        $db = new Manager($app);
        $this->schema = 'messaging_'.bin2hex(random_bytes(8));
        $db->addConnection([
            'driver' => 'pgsql', 'host' => $socket, 'port' => 16549,
            'database' => 'fspbx_migration_test', 'username' => 'postgres', 'password' => '',
            'charset' => 'utf8', 'search_path' => $this->schema,
        ]);
        $app->instance('db', $db->getDatabaseManager());
        $app->instance('db.schema', $db->getConnection()->getSchemaBuilder());
        DB::statement('CREATE SCHEMA '.$this->schema);
        Schema::create('messages', function (Blueprint $t) {
            $t->uuid('message_uuid')->primary();
            $t->uuid('domain_uuid')->nullable();
            $t->string('source')->nullable();
            $t->string('destination')->nullable();
            $t->string('direction')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('read_at')->nullable();
        });
        Schema::create('v_users', fn (Blueprint $t) => $t->uuid('user_uuid')->primary());
        $this->migration = require base_path('database/migrations/2026_10_01_000001_create_shared_messaging_tables.php');
    }

    protected function tearDown(): void
    {
        if (! isset($this->previousContainer)) {
            parent::tearDown();
            return;
        }
        if ($this->schema) DB::statement('DROP SCHEMA '.$this->schema.' CASCADE');
        DB::disconnect();
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_fresh_schema_has_uuid_keys_and_reruns_without_schema_changes(): void
    {
        $this->migration->up();
        $schema = $this->snapshot();
        DB::enableQueryLog();
        $this->migration->up();
        $writes = array_filter(DB::getQueryLog(), fn ($query) => preg_match('/^\s*(create|alter|drop|insert|update|delete)\b/i', $query['query']));
        DB::disableQueryLog();
        $this->assertSame([], array_values($writes), 'A complete migration rerun should only inspect existing state.');
        $this->assertSame($schema, $this->snapshot());
        foreach (self::TABLES as $table => $primary) {
            $this->assertTrue(Schema::hasIndex($table, [$primary], 'primary'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'id'), $table);
            $column = collect(Schema::getColumns($table))->firstWhere('name', $primary);
            $this->assertSame('uuid', $column['type_name']);
        }
        $this->assertCount(2, Schema::getForeignKeys('message_user_reads'));
        $this->assertCount(2, Schema::getForeignKeys('message_user_hides'));
        $this->assertCount(1, Schema::getForeignKeys('messages'));
        $this->assertTrue(Schema::hasIndex('messages', 'messages_conversation_unread_index'));
        $this->assertTrue(Schema::hasIndex('messages', 'messages_group_history_index'));
    }

    public function test_existing_final_schema_data_and_uuid_values_are_preserved(): void
    {
        $this->migration->up();
        $this->message('message');
        DB::table('v_users')->insert(['user_uuid' => $this->id('user')]);
        DB::table('message_user_reads')->insert([
            'message_user_read_uuid' => $this->id('read'), 'domain_uuid' => $this->id('domain'),
            'message_uuid' => $this->id('message'), 'user_uuid' => $this->id('user'), 'read_at' => '2026-09-01 12:00:00',
        ]);
        DB::table('message_user_hides')->insert([
            'message_user_hide_uuid' => $this->id('hide'), 'domain_uuid' => $this->id('domain'),
            'message_uuid' => $this->id('message'), 'user_uuid' => $this->id('user'), 'history_deleted' => true,
        ]);
        DB::table('sms_destination_members')->insert([
            'sms_destination_member_uuid' => $this->id('saved-member-uuid'), 'domain_uuid' => $this->id('domain'),
            'sms_destination_uuid' => $this->id('route'), 'extension_uuid' => $this->id('extension'),
        ]);
        $before = $this->rows();
        $this->migration->up();
        $this->assertEquals($before, $this->rows());
    }

    public function test_repairs_missing_tables_columns_indexes_and_foreign_keys(): void
    {
        // A partially applied final schema, including populated tables.
        Schema::create('message_user_hides', function (Blueprint $t) {
            $t->uuid('message_user_hide_uuid')->primary();
            $t->uuid('domain_uuid');
            $t->uuid('user_uuid');
            $t->uuid('message_uuid');
            $t->string('custom_note')->nullable();
        });
        Schema::table('messages', fn (Blueprint $t) => $t->uuid('message_group_uuid')->nullable());
        $this->message('message');
        DB::table('v_users')->insert(['user_uuid' => $this->id('user')]);
        DB::table('message_user_hides')->insert([
            'message_user_hide_uuid' => $this->id('hide'), 'domain_uuid' => $this->id('domain'),
            'message_uuid' => $this->id('message'), 'user_uuid' => $this->id('user'), 'custom_note' => 'keep me',
        ]);
        $this->migration->up();
        $row = DB::table('message_user_hides')->first();
        $this->assertFalse((bool) $row->history_deleted);
        $this->assertSame('keep me', $row->custom_note);
        $this->assertCount(2, Schema::getForeignKeys('message_user_hides'));
        $this->assertTrue(Schema::hasIndex('message_user_hides', ['user_uuid', 'message_uuid'], 'unique'));
        $this->assertCount(1, Schema::getForeignKeys('messages'));

        Schema::drop('message_user_reads');
        Schema::table('ringotel_message_syncs', fn (Blueprint $t) => $t->dropColumn('targets_initialized_at'));
        Schema::table('messages', fn (Blueprint $t) => $t->dropIndex('messages_group_history_index'));
        $this->migration->up();
        $this->assertTrue(Schema::hasTable('message_user_reads'));
        $this->assertTrue(Schema::hasColumn('ringotel_message_syncs', 'targets_initialized_at'));
        $this->assertTrue(Schema::hasIndex('messages', 'messages_group_history_index'));
        DB::table('messages')->where('message_uuid', $this->id('message'))->delete();
        $this->assertSame(0, DB::table('message_user_hides')->count());
    }

    public function test_upgrades_the_first_original_migration_and_preserves_uncertain_delivery(): void
    {
        $this->legacyTables();
        $this->legacyConversation();
        $this->legacySync('message');
        $oid = $this->tableIdentity('ringotel_conversations');
        $this->migration->up();
        $conversation = DB::table('ringotel_conversations')->first();
        $this->assertSame($this->conversationUuid(), $conversation->ringotel_conversation_uuid);
        $this->assertSame('retained-session', $conversation->session_id);
        $this->assertSame($oid, $this->tableIdentity('ringotel_conversations'));
        $target = DB::table('ringotel_message_deliveries')->first();
        $this->assertSame('uncertain', $target->status);
        $this->assertSame($this->conversationUuid(), $target->ringotel_conversation_uuid);
        $this->assertSame(['text' => ['status' => 'sending', 'message_id' => 'keep-receipt']], json_decode($target->parts, true));
        $this->assertNotNull(DB::table('ringotel_message_syncs')->value('targets_initialized_at'));
        $before = $this->rows();
        $this->migration->up();
        $this->assertEquals($before, $this->rows());
    }

    public function test_preserves_shared_legacy_targets_and_already_assigned_uuid_during_conversion(): void
    {
        $this->legacyTables(shared: true);
        $this->legacyConversation();
        $this->legacySync('message');
        // An interrupted conversion may already have assigned some UUIDs.
        Schema::table('ringotel_message_deliveries', fn (Blueprint $t) => $t->uuid('ringotel_message_delivery_uuid')->nullable());
        DB::table('ringotel_message_deliveries')->insert([
            'id' => hash('sha256', 'delivery'), 'ringotel_message_delivery_uuid' => $this->id('existing-delivery'),
            'message_uuid' => $this->id('message'), 'conversation_id' => hash('sha256', 'conversation'),
            'status' => 'success', 'parts' => json_encode(['text' => ['status' => 'success', 'message_id' => 'accepted']]),
        ]);
        DB::table('sms_destination_members')->insert([
            'domain_uuid' => $this->id('domain'), 'sms_destination_uuid' => $this->id('route'), 'extension_uuid' => $this->id('extension'),
        ]);
        $this->migration->up();
        $this->assertSame(1, DB::table('ringotel_message_deliveries')->count());
        $target = DB::table('ringotel_message_deliveries')->first();
        $this->assertSame($this->id('existing-delivery'), $target->ringotel_message_delivery_uuid);
        $this->assertSame('success', $target->status);
        $this->assertSame('accepted', json_decode($target->parts, true)['text']['message_id']);
        $member = DB::table('sms_destination_members')->first();
        $this->assertSame($this->id('fspbx:sms-destination-member:'.$member->sms_destination_uuid.'|'.$member->extension_uuid), $member->sms_destination_member_uuid);
        $before = $this->rows();
        $this->migration->up();
        $this->assertEquals($before, $this->rows());
    }

    public function test_backfills_more_than_one_chunk_without_skipping_rows(): void
    {
        $this->legacyTables(shared: true);
        for ($i = 0; $i < 405; $i++) {
            $this->legacyConversation('conversation-'.$i);
            $this->legacySync('message-'.$i, 'conversation-'.$i);
            DB::table('sms_destination_members')->insert([
                'domain_uuid' => $this->id('domain'), 'sms_destination_uuid' => $this->id('route'), 'extension_uuid' => $this->id('extension-'.$i),
            ]);
        }
        $this->migration->up();
        $this->assertSame(405, DB::table('ringotel_message_deliveries')->count());
        $this->assertSame(405, DB::table('sms_destination_members')->whereNotNull('sms_destination_member_uuid')->count());
        $this->assertSame(405, DB::table('ringotel_conversations')->whereNotNull('ringotel_conversation_uuid')->count());
        $this->assertSame(0, DB::table('ringotel_message_syncs')->whereNull('targets_initialized_at')->count());
        $before = $this->rows();
        $this->migration->up();
        $this->assertEquals($before, $this->rows());
    }

    public function test_unrecoverable_existing_data_rolls_back_without_recreating_tables(): void
    {
        $this->legacyTables();
        $this->legacyConversation();
        $this->legacySync('message');
        DB::table('ringotel_message_syncs')->update(['conversation_id' => 'invalid-key']);
        try {
            $this->migration->up();
            $this->fail('Invalid keys must not be silently replaced.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Invalid legacy Ringotel conversation key', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('ringotel_conversations', 'id'));
        $this->assertSame('retained-session', DB::table('ringotel_conversations')->value('session_id'));
        $this->assertSame('invalid-key', DB::table('ringotel_message_syncs')->value('conversation_id'));
    }

    public function test_rollback_does_not_delete_adopted_tables(): void
    {
        $this->migration->up();
        try {
            $this->migration->down();
            $this->fail('A destructive rollback must require restoring the matching backup.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('database backup', $e->getMessage());
        }
        foreach (array_keys(self::TABLES) as $table) $this->assertTrue(Schema::hasTable($table));
    }

    private function legacyTables(bool $shared = false): void
    {
        Schema::create('ringotel_conversations', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->uuid('domain_uuid');
            $t->uuid('extension_uuid');
            foreach (['org_id', 'user_id', 'local_number', 'remote_number', 'remote_identifier'] as $column) $t->string($column);
            $t->string('session_id')->nullable();
            $t->timestamp('observed_at')->nullable();
            $t->timestamps();
            $t->index(['org_id', 'session_id']);
        });
        Schema::create('ringotel_message_syncs', function (Blueprint $t) use ($shared) {
            $t->uuid('message_uuid')->primary();
            $t->foreign('message_uuid')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $t->string('conversation_id', 64)->nullable();
            $t->string('status')->default('pending');
            $t->json('parts')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index('status');
            if ($shared) $t->timestamp('targets_initialized_at')->nullable();
        });
        if (! $shared) return;
        Schema::create('ringotel_message_deliveries', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->uuid('message_uuid');
            $t->foreign('message_uuid')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $t->string('conversation_id', 64);
            $t->string('status')->default('pending');
            $t->json('parts')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['message_uuid', 'conversation_id']);
        });
        Schema::create('sms_destination_members', function (Blueprint $t) {
            $t->uuid('domain_uuid');
            $t->uuid('sms_destination_uuid');
            $t->uuid('extension_uuid');
            $t->primary(['sms_destination_uuid', 'extension_uuid']);
            $t->index(['domain_uuid', 'extension_uuid']);
        });
    }

    private function legacyConversation(string $key = 'conversation'): void
    {
        DB::table('ringotel_conversations')->insert([
            'id' => hash('sha256', $key), 'domain_uuid' => $this->id('domain'), 'extension_uuid' => $this->id('extension'),
            'org_id' => 'org', 'user_id' => 'user', 'local_number' => '+12025550100', 'remote_number' => '+12025550101',
            'remote_identifier' => '+12025550101', 'session_id' => 'retained-session',
        ]);
    }

    private function legacySync(string $message, string $conversation = 'conversation'): void
    {
        $this->message($message);
        DB::table('ringotel_message_syncs')->insert([
            'message_uuid' => $this->id($message), 'conversation_id' => hash('sha256', $conversation), 'status' => 'uncertain',
            'parts' => json_encode(['text' => ['status' => 'sending', 'message_id' => 'keep-receipt']]),
            'error' => 'Review request outcome', 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:01:00',
        ]);
    }

    private function message(string $key): void
    {
        DB::table('messages')->insert(['message_uuid' => $this->id($key), 'read_at' => '2026-09-01 12:00:00']);
    }

    private function id(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $key)->toString();
    }

    private function conversationUuid(): string
    {
        return $this->id('fspbx:ringotel:conversation:'.hash('sha256', 'conversation'));
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (array_merge(array_keys(self::TABLES), ['messages']) as $table) {
            $result[$table] = [Schema::getColumns($table), Schema::getIndexes($table), Schema::getForeignKeys($table)];
        }
        return $result;
    }

    private function rows(): array
    {
        $result = ['messages' => DB::table('messages')->orderBy('message_uuid')->get()->all()];
        foreach (self::TABLES as $table => $key) $result[$table] = DB::table($table)->orderBy($key)->get()->all();
        return $result;
    }

    private function tableIdentity(string $table): string
    {
        return DB::selectOne('SELECT ?::regclass::oid::text AS oid', [$table])->oid;
    }
}
