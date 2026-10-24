<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

return new class extends Migration
{
    public function up(): void
    {
        // A new filename also runs on installations that recorded any of the
        // seven original PR migrations. Never edit their migration history.
        DB::transaction(function () {
            foreach (['messages', 'v_users'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("The shared messaging migration requires the {$table} table.");
                }
            }

            $this->convertLegacyKeys();

            foreach ($this->tables() as $name => $definition) {
                $this->ensureTable($name, $definition);
            }

            if (! Schema::hasColumn('messages', 'message_group_uuid')) {
                Schema::table('messages', fn (Blueprint $table) => $table->uuid('message_group_uuid')->nullable());
            }
            $this->ensureForeign('messages', 'message_group_uuid', 'message_groups', 'message_group_uuid');
            $this->ensureIndex('messages', ['domain_uuid', 'source', 'destination', 'direction', 'created_at'], 'messages_conversation_unread_index');
            $this->ensureIndex('messages', ['domain_uuid', 'message_group_uuid', 'created_at'], 'messages_group_history_index');

            $this->preserveLegacyDeliveries();
        });
    }

    private function tables(): array
    {
        return [
            'ringotel_conversations' => [
                'primary' => 'ringotel_conversation_uuid',
                'columns' => [
                    'ringotel_conversation_uuid' => fn ($t) => $t->uuid('ringotel_conversation_uuid'),
                    'conversation_key' => fn ($t) => $t->string('conversation_key', 64),
                    'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                    'extension_uuid' => fn ($t) => $t->uuid('extension_uuid'),
                    'org_id' => fn ($t) => $t->string('org_id'),
                    'user_id' => fn ($t) => $t->string('user_id'),
                    'local_number' => fn ($t) => $t->string('local_number'),
                    'remote_number' => fn ($t) => $t->string('remote_number'),
                    'remote_identifier' => fn ($t) => $t->string('remote_identifier'),
                    'session_id' => fn ($t) => $t->string('session_id')->nullable(),
                    'observed_at' => fn ($t) => $t->timestamp('observed_at')->nullable(),
                    'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(),
                    'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable(),
                ],
                'indexes' => [
                    [['conversation_key'], 'ringotel_conversations_conversation_key_unique', 'unique'],
                    [['org_id', 'session_id'], 'ringotel_conversation_session_lookup'],
                ],
            ],
            'ringotel_message_syncs' => [
                'primary' => 'message_uuid',
                'columns' => [
                    'message_uuid' => fn ($t) => $t->uuid('message_uuid'),
                    'ringotel_conversation_uuid' => fn ($t) => $t->uuid('ringotel_conversation_uuid')->nullable(),
                    'status' => fn ($t) => $t->string('status')->default('pending'),
                    'parts' => fn ($t) => $t->json('parts')->nullable(),
                    'error' => fn ($t) => $t->text('error')->nullable(),
                    'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(),
                    'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable(),
                    'targets_initialized_at' => fn ($t) => $t->timestamp('targets_initialized_at')->nullable(),
                ],
                'indexes' => [[['status'], 'ringotel_sync_status_lookup']],
                'foreign' => [['message_uuid', 'messages', 'message_uuid', 'cascade']],
            ],
            'ringotel_message_deliveries' => [
                'primary' => 'ringotel_message_delivery_uuid',
                'columns' => [
                    'ringotel_message_delivery_uuid' => fn ($t) => $t->uuid('ringotel_message_delivery_uuid'),
                    'delivery_key' => fn ($t) => $t->string('delivery_key', 64),
                    'message_uuid' => fn ($t) => $t->uuid('message_uuid'),
                    'ringotel_conversation_uuid' => fn ($t) => $t->uuid('ringotel_conversation_uuid'),
                    'status' => fn ($t) => $t->string('status')->default('pending'),
                    'parts' => fn ($t) => $t->json('parts')->nullable(),
                    'error' => fn ($t) => $t->text('error')->nullable(),
                    'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(),
                    'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable(),
                ],
                'indexes' => [
                    [['delivery_key'], 'ringotel_message_deliveries_delivery_key_unique', 'unique'],
                    [['message_uuid', 'ringotel_conversation_uuid'], 'ringotel_delivery_recipient_unique', 'unique'],
                ],
                'foreign' => [['message_uuid', 'messages', 'message_uuid', 'cascade']],
            ],
            'sms_destination_members' => [
                'primary' => 'sms_destination_member_uuid',
                'columns' => [
                    'sms_destination_member_uuid' => fn ($t) => $t->uuid('sms_destination_member_uuid'),
                    'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                    'sms_destination_uuid' => fn ($t) => $t->uuid('sms_destination_uuid'),
                    'extension_uuid' => fn ($t) => $t->uuid('extension_uuid'),
                ],
                'indexes' => [
                    [['sms_destination_uuid', 'extension_uuid'], 'sms_destination_member_unique', 'unique'],
                    [['domain_uuid', 'extension_uuid'], 'sms_member_extension_lookup'],
                ],
            ],
            'message_user_reads' => [
                'primary' => 'message_user_read_uuid',
                'columns' => [
                    'message_user_read_uuid' => fn ($t) => $t->uuid('message_user_read_uuid'),
                    'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                    'user_uuid' => fn ($t) => $t->uuid('user_uuid'),
                    'message_uuid' => fn ($t) => $t->uuid('message_uuid'),
                    'read_at' => fn ($t) => $t->timestampTz('read_at'),
                ],
                'indexes' => [[['user_uuid', 'message_uuid'], 'message_user_reads_user_uuid_message_uuid_unique', 'unique']],
                'foreign' => [
                    ['message_uuid', 'messages', 'message_uuid', 'cascade'],
                    ['user_uuid', 'v_users', 'user_uuid', 'cascade'],
                ],
            ],
            'message_user_hides' => [
                'primary' => 'message_user_hide_uuid',
                'columns' => [
                    'message_user_hide_uuid' => fn ($t) => $t->uuid('message_user_hide_uuid'),
                    'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                    'user_uuid' => fn ($t) => $t->uuid('user_uuid'),
                    'message_uuid' => fn ($t) => $t->uuid('message_uuid'),
                    'history_deleted' => fn ($t) => $t->boolean('history_deleted')->default(false),
                ],
                'indexes' => [[['user_uuid', 'message_uuid'], 'message_user_hides_user_uuid_message_uuid_unique', 'unique']],
                'foreign' => [
                    ['message_uuid', 'messages', 'message_uuid', 'cascade'],
                    ['user_uuid', 'v_users', 'user_uuid', 'cascade'],
                ],
            ],
            'message_groups' => [
                'primary' => 'message_group_uuid',
                'columns' => [
                    'message_group_uuid' => fn ($t) => $t->uuid('message_group_uuid'),
                    'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                    'local_number' => fn ($t) => $t->string('local_number', 20),
                    'recipients' => fn ($t) => $t->json('recipients'),
                    'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(),
                    'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable(),
                ],
                'indexes' => [[['domain_uuid', 'local_number'], 'message_groups_domain_uuid_local_number_index']],
            ],
        ];
    }

    private function ensureTable(string $name, array $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table) use ($definition) {
                foreach ($definition['columns'] as $column) $column($table);
                $table->primary($definition['primary']);
                foreach ($definition['foreign'] ?? [] as [$column, $target, $key, $delete]) {
                    $table->foreign($column)->references($key)->on($target)->onDelete($delete);
                }
            });
        } else {
            $existing = array_flip(Schema::getColumnListing($name));
            $missing = array_diff_key($definition['columns'], $existing);
            if ($missing) {
                Schema::table($name, function (Blueprint $table) use ($missing) {
                    foreach ($missing as $column) $column($table);
                });
            }
            $this->ensurePrimary($name, $definition['primary']);
        }

        foreach ($definition['indexes'] ?? [] as $index) $this->ensureIndex($name, ...$index);
        foreach ($definition['foreign'] ?? [] as $foreign) $this->ensureForeign($name, ...$foreign);
    }

    private function ensureIndex(string $table, array $columns, string $name, string $type = 'index'): void
    {
        if (Schema::hasIndex($table, $columns, $type === 'unique' ? 'unique' : null)) return;
        Schema::table($table, fn (Blueprint $t) => $t->{$type}($columns, $name));
    }

    private function ensureForeign(string $table, string $column, string $target, string $key, string $delete = 'no action'): void
    {
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if ($foreign['columns'] === [$column] && $foreign['foreign_table'] === $target && $foreign['foreign_columns'] === [$key]) return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->foreign($column)->references($key)->on($target)->onDelete($delete));
    }

    private function ensurePrimary(string $table, string $column): void
    {
        $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);
        if (($primary['columns'] ?? []) === [$column]) return;
        Schema::table($table, function (Blueprint $t) use ($primary, $column) {
            if ($primary) $t->dropPrimary($primary['name']);
            $t->primary($column);
        });
    }

    private function renameLegacyColumn(string $table, string $old, string $new): void
    {
        if (! Schema::hasColumn($table, $old)) return;
        if (Schema::hasColumn($table, $new)) {
            throw new RuntimeException("Both {$table}.{$old} and {$new} exist; reconcile the legacy keys before migrating.");
        }
        Schema::table($table, fn (Blueprint $t) => $t->renameColumn($old, $new));
    }

    private function convertLegacyKeys(): void
    {
        // PostgreSQL changes are in place: retain table identity, grants,
        // publications, rows, and already assigned UUIDs.
        foreach (['ringotel_conversations' => ['conversation_key', 'ringotel_conversation_uuid'],
            'ringotel_message_deliveries' => ['delivery_key', 'ringotel_message_delivery_uuid']] as $table => [$key, $uuid]) {
            if (! Schema::hasTable($table)) continue;
            $this->renameLegacyColumn($table, 'id', $key);
            if (! Schema::hasColumn($table, $key)) continue;
            if (! Schema::hasColumn($table, $uuid)) {
                Schema::table($table, fn (Blueprint $t) => $t->uuid($uuid)->nullable());
            }
            DB::table($table)->whereNull($uuid)->chunkById(200, function ($rows) use ($table, $key, $uuid) {
                foreach ($rows as $row) {
                    $prefix = $table === 'ringotel_conversations' ? 'conversation' : 'delivery';
                    DB::table($table)->where($key, $row->{$key})->update([
                        $uuid => $this->uuid('fspbx:ringotel:'.$prefix.':'.$row->{$key}),
                    ]);
                }
            }, $key);
            $this->ensurePrimary($table, $uuid);
        }

        foreach (['ringotel_message_syncs', 'ringotel_message_deliveries'] as $table) {
            if (! Schema::hasTable($table)) continue;
            $this->renameLegacyColumn($table, 'conversation_id', 'ringotel_conversation_uuid');
            if (! Schema::hasColumn($table, 'ringotel_conversation_uuid')) continue;
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'ringotel_conversation_uuid');
            if ($column['type_name'] === 'uuid') continue;
            $key = $table === 'ringotel_message_syncs' ? 'message_uuid' : 'ringotel_message_delivery_uuid';
            DB::table($table)->whereNotNull('ringotel_conversation_uuid')->orderBy($key)->chunk(200, function ($rows) use ($table, $key) {
                foreach ($rows as $row) {
                    $old = $row->ringotel_conversation_uuid;
                    if (Uuid::isValid($old)) continue;
                    if (! preg_match('/^[a-f0-9]{64}$/D', $old)) throw new RuntimeException("Invalid legacy Ringotel conversation key in {$table}.");
                    $uuid = Schema::hasTable('ringotel_conversations')
                        ? DB::table('ringotel_conversations')->where('conversation_key', $old)->value('ringotel_conversation_uuid') : null;
                    DB::table($table)->where($key, $row->{$key})->update([
                        'ringotel_conversation_uuid' => $uuid ?? $this->uuid('fspbx:ringotel:conversation:'.$old),
                    ]);
                }
            });
            DB::statement('ALTER TABLE '.$table.' ALTER COLUMN ringotel_conversation_uuid TYPE uuid USING ringotel_conversation_uuid::uuid');
        }

        $table = 'sms_destination_members';
        if (Schema::hasTable($table) && Schema::hasColumns($table, ['sms_destination_uuid', 'extension_uuid'])) {
            if (! Schema::hasColumn($table, 'sms_destination_member_uuid')) {
                Schema::table($table, fn (Blueprint $t) => $t->uuid('sms_destination_member_uuid')->nullable());
            }
            // Rows leave this predicate as they are filled; OFFSET would skip
            // the next batch. The old primary key here is composite.
            while (($rows = DB::table($table)->whereNull('sms_destination_member_uuid')
                ->orderBy('sms_destination_uuid')->orderBy('extension_uuid')->limit(200)->get())->isNotEmpty()) {
                foreach ($rows as $row) {
                    DB::table($table)->where('sms_destination_uuid', $row->sms_destination_uuid)->where('extension_uuid', $row->extension_uuid)->update([
                        'sms_destination_member_uuid' => $this->uuid('fspbx:sms-destination-member:'.$row->sms_destination_uuid.'|'.$row->extension_uuid),
                    ]);
                }
            }
            $this->ensurePrimary($table, 'sms_destination_member_uuid');
        }
    }

    private function preserveLegacyDeliveries(): void
    {
        // Only initialize previously uninitialized targets. Existing success,
        // sending and uncertain results must never be reset or replayed.
        DB::table('ringotel_message_syncs')->whereNull('targets_initialized_at')->whereNotNull('ringotel_conversation_uuid')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $key = hash('sha256', $row->message_uuid.'|'.$row->ringotel_conversation_uuid);
                    DB::table('ringotel_message_deliveries')->insertOrIgnore([
                        'ringotel_message_delivery_uuid' => $this->uuid('fspbx:ringotel:delivery:'.$key),
                        'delivery_key' => $key, 'message_uuid' => $row->message_uuid,
                        'ringotel_conversation_uuid' => $row->ringotel_conversation_uuid,
                        'status' => $row->status, 'parts' => $row->parts, 'error' => $row->error,
                        'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                    ]);
                    DB::table('ringotel_message_syncs')->where('message_uuid', $row->message_uuid)->update([
                        'targets_initialized_at' => $row->updated_at ?? $row->created_at ?? now(),
                    ]);
                }
            }, 'message_uuid');
    }

    private function uuid(string $key): string
    {
        // Stable on independently migrated replicas; never regenerate saved IDs.
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $key)->toString();
    }

    public function down(): void
    {
        // This migration can adopt existing tables and convert populated keys.
        // Dropping them would delete data that predates this migration.
        throw new RuntimeException('Restore the matching database backup and application code to reverse the shared messaging migration.');
    }
};
