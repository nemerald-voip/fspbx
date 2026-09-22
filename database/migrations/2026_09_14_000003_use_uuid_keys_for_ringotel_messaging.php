<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->migratePostgres();
            return;
        }
        // SQLite test databases need replacement tables to change primary keys.
        Schema::create('ringotel_conversations_uuid_replacement', function (Blueprint $t) {
            $t->uuid('ringotel_conversation_uuid')->primary();
            $t->string('conversation_key', 64)->unique();
            $t->uuid('domain_uuid');
            $t->uuid('extension_uuid');
            foreach (['org_id', 'user_id', 'local_number', 'remote_number', 'remote_identifier'] as $column) $t->string($column);
            $t->string('session_id')->nullable();
            $t->timestamp('observed_at')->nullable();
            $t->timestamps();
            $t->index(['org_id', 'session_id'], 'ringotel_conversation_session_lookup');
        });
        Schema::create('ringotel_message_syncs_uuid_replacement', function (Blueprint $t) {
            $t->uuid('message_uuid')->primary();
            $t->foreign('message_uuid', 'ringotel_sync_message_fk')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $t->uuid('ringotel_conversation_uuid')->nullable();
            $t->string('status')->default('pending');
            $t->json('parts')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->timestamp('targets_initialized_at')->nullable();
            $t->index('status', 'ringotel_sync_status_lookup');
        });
        Schema::create('ringotel_message_deliveries_uuid_replacement', function (Blueprint $t) {
            $t->uuid('ringotel_message_delivery_uuid')->primary();
            $t->string('delivery_key', 64)->unique();
            $t->uuid('message_uuid');
            $t->foreign('message_uuid', 'ringotel_delivery_message_fk')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $t->uuid('ringotel_conversation_uuid');
            $t->string('status')->default('pending');
            $t->json('parts')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['message_uuid', 'ringotel_conversation_uuid'], 'ringotel_delivery_recipient_unique');
        });
        Schema::create('sms_destination_members_uuid_replacement', function (Blueprint $t) {
            $t->uuid('sms_destination_member_uuid')->primary();
            $t->uuid('domain_uuid');
            $t->uuid('sms_destination_uuid');
            $t->uuid('extension_uuid');
            $t->unique(['sms_destination_uuid', 'extension_uuid'], 'sms_destination_member_unique');
            $t->index(['domain_uuid', 'extension_uuid'], 'sms_member_extension_lookup');
        });

        // Standard UUIDv5 gives the same UUID to references whose conversation
        // record was not yet created (for example a previously failed MMS).
        $conversationUuid = fn ($key) => $key === null ? null
            : Uuid::uuid5(Uuid::NAMESPACE_URL, 'fspbx:ringotel:conversation:'.$key)->toString();
        $tables = ['ringotel_conversations', 'ringotel_message_syncs', 'ringotel_message_deliveries', 'sms_destination_members'];
        foreach ($tables as $table) {
            $order = match ($table) {
                'ringotel_conversations', 'ringotel_message_deliveries' => 'id',
                'ringotel_message_syncs' => 'message_uuid',
                default => 'sms_destination_uuid',
            };
            $query = DB::table($table)->orderBy($order);
            if ($table === 'sms_destination_members') $query->orderBy('extension_uuid');
            $query->chunk(200, function ($rows) use ($table, $conversationUuid) {
                $converted = [];
                foreach ($rows as $row) {
                    $data = (array) $row;
                    if ($table === 'ringotel_conversations') {
                        $data['ringotel_conversation_uuid'] = $conversationUuid($data['id']);
                        $data['conversation_key'] = $data['id'];
                        unset($data['id']);
                    } elseif ($table === 'sms_destination_members') {
                        $data['sms_destination_member_uuid'] = (string) Str::uuid();
                    } else {
                        $data['ringotel_conversation_uuid'] = $conversationUuid($data['conversation_id']);
                        unset($data['conversation_id']);
                        if ($table === 'ringotel_message_deliveries') {
                            $data['ringotel_message_delivery_uuid'] = (string) Str::uuid();
                            $data['delivery_key'] = $data['id'];
                            unset($data['id']);
                        }
                    }
                    $converted[] = $data;
                }
                DB::table($table.'_uuid_replacement')->insert($converted);
            });
            if (DB::table($table)->count() !== DB::table($table.'_uuid_replacement')->count()) {
                throw new RuntimeException('UUID migration row count mismatch for '.$table);
            }
        }
        foreach ($tables as $table) {
            Schema::drop($table);
            Schema::rename($table.'_uuid_replacement', $table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the matching pre-migration database backup and application code to reverse this UUID conversion.');
    }

    private function migratePostgres(): void
    {
        // Alter the existing PostgreSQL tables in place, preserving grants,
        // publications, foreign keys, and table identity.
        DB::statement('LOCK TABLE ringotel_conversations, ringotel_message_syncs, ringotel_message_deliveries, sms_destination_members IN ACCESS EXCLUSIVE MODE');
        $keys = [
            'ringotel_conversations' => 'ringotel_conversation_uuid',
            'ringotel_message_deliveries' => 'ringotel_message_delivery_uuid',
            'sms_destination_members' => 'sms_destination_member_uuid',
        ];
        foreach ($keys as $table => $key) {
            Schema::table($table, fn (Blueprint $t) => $t->uuid($key)->nullable());
        }
        DB::table('ringotel_conversations')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('ringotel_conversations')->where('id', $row->id)->update([
                    'ringotel_conversation_uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'fspbx:ringotel:conversation:'.$row->id)->toString(),
                ]);
            }
        });
        foreach (['ringotel_message_syncs', 'ringotel_message_deliveries'] as $table) {
            DB::table($table)->orderBy($table === 'ringotel_message_syncs' ? 'message_uuid' : 'id')->chunk(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $data = ['conversation_id' => $row->conversation_id === null ? null
                        : Uuid::uuid5(Uuid::NAMESPACE_URL, 'fspbx:ringotel:conversation:'.$row->conversation_id)->toString()];
                    if ($table === 'ringotel_message_deliveries') $data['ringotel_message_delivery_uuid'] = (string) Str::uuid();
                    DB::table($table)->where($table === 'ringotel_message_syncs' ? 'message_uuid' : 'id',
                        $table === 'ringotel_message_syncs' ? $row->message_uuid : $row->id)->update($data);
                }
            });
            DB::statement('ALTER TABLE '.$table.' RENAME COLUMN conversation_id TO ringotel_conversation_uuid');
            DB::statement('ALTER TABLE '.$table.' ALTER COLUMN ringotel_conversation_uuid TYPE uuid USING ringotel_conversation_uuid::uuid');
        }
        DB::table('sms_destination_members')->orderBy('sms_destination_uuid')->orderBy('extension_uuid')->chunk(200, function ($rows) {
            foreach ($rows as $row) DB::table('sms_destination_members')
                ->where('sms_destination_uuid', $row->sms_destination_uuid)->where('extension_uuid', $row->extension_uuid)
                ->update(['sms_destination_member_uuid' => (string) Str::uuid()]);
        });
        foreach ($keys as $table => $key) {
            $constraint = DB::selectOne("SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'p'", [$table])->conname;
            DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT "'.str_replace('"', '""', $constraint).'"');
            DB::statement('ALTER TABLE '.$table.' ALTER COLUMN '.$key.' SET NOT NULL, ADD PRIMARY KEY ('.$key.')');
        }
        DB::statement('ALTER TABLE ringotel_conversations RENAME COLUMN id TO conversation_key');
        DB::statement('ALTER TABLE ringotel_message_deliveries RENAME COLUMN id TO delivery_key');
        Schema::table('ringotel_conversations', fn (Blueprint $t) => $t->unique('conversation_key'));
        Schema::table('ringotel_message_deliveries', fn (Blueprint $t) => $t->unique('delivery_key'));
        Schema::table('sms_destination_members', fn (Blueprint $t) => $t->unique(['sms_destination_uuid', 'extension_uuid']));
    }
};
