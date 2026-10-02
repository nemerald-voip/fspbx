<?php

namespace Tests\Unit;

use App\Services\Messaging\MessageReadService;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class MessageReadServiceTest extends TestCase
{
    private Manager $db;
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->db = new Manager();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();

        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('messages', function (Blueprint $table) {
            $table->string('message_uuid')->primary();
            foreach (['domain_uuid', 'source', 'destination', 'direction', 'message_group_uuid'] as $column) {
                $table->string($column)->nullable();
            }
            $table->json('delivery_meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
        });
        $schema->create('message_user_reads', function (Blueprint $table) {
            foreach (['domain_uuid', 'user_uuid', 'message_uuid'] as $column) {
                $table->string($column);
            }
            $table->timestamp('read_at');
        });
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    public function test_upgrade_preserves_read_history_and_keeps_unread_history_unread(): void
    {
        $this->message('previously-read', ['read_at' => '2026-09-01 12:05:00']);
        $this->message('previously-unread');
        $this->message('another-account', ['domain_uuid' => 'another-account']);

        // The new read table is empty immediately after upgrading. The old
        // shared read state must still apply to everyone viewing this number.
        $reads = new MessageReadService();
        foreach (['alice', 'bob'] as $user) {
            $this->assertSame(['previously-unread'], $reads->unread('account', $user)->pluck('message_uuid')->all());
        }
        $this->assertSame(0, $this->db->getConnection()->table('message_user_reads')->count());
    }

    public function test_new_messages_keep_independent_read_state_for_each_user(): void
    {
        $this->message('new-message', ['delivery_meta' => json_encode(['ringotel_tracking' => 1])]);
        $this->db->getConnection()->table('message_user_reads')->insert([
            'domain_uuid' => 'account', 'user_uuid' => 'alice', 'message_uuid' => 'new-message',
            'read_at' => '2026-09-01 12:05:00',
        ]);

        $reads = new MessageReadService();
        $this->assertSame([], $reads->unread('account', 'alice')->pluck('message_uuid')->all());
        $this->assertSame(['new-message'], $reads->unread('account', 'bob')->pluck('message_uuid')->all());
        $this->assertNull($this->db->getConnection()->table('messages')->value('read_at'));
    }

    public function test_an_accepted_reply_still_clears_unread_for_the_team(): void
    {
        $this->message('incoming');
        $this->message('reply', [
            'direction' => 'out', 'source' => '+12025550100', 'destination' => '+12025550101',
            'created_at' => '2026-09-01 12:05:00',
            'delivery_meta' => json_encode(['outbound' => ['provider' => ['accepted_at' => '2026-09-01T12:05:01Z']]]),
        ]);

        $reads = new MessageReadService();
        foreach (['alice', 'bob'] as $user) {
            $this->assertSame([], $reads->unread('account', $user)->pluck('message_uuid')->all());
        }
    }

    private function message(string $id, array $attributes = []): void
    {
        $this->db->getConnection()->table('messages')->insert($attributes + [
            'message_uuid' => $id, 'domain_uuid' => 'account', 'direction' => 'in',
            'source' => '+12025550101', 'destination' => '+12025550100',
            'created_at' => '2026-09-01 12:00:00',
        ]);
    }
}
