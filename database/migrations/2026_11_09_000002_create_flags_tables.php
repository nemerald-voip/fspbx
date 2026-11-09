<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New filename also runs where original PR migrations were recorded.
        // This installs schema only, never settings, grants, menus or dialplans.
        DB::transaction(function () {
            foreach ($this->tables() as $name => $columns) {
                if (! Schema::hasTable($name)) {
                    Schema::create($name, function (Blueprint $table) use ($columns) {
                        foreach ($columns as $define) {
                            $define($table);
                        }
                    });
                } else {
                    foreach ($columns as $column => $define) {
                        if (! Schema::hasColumn($name, $column)) {
                            Schema::table($name, fn (Blueprint $table) => $define($table));
                        }
                    }
                }

                $primary = $name === 'flag_extensions' ? ['flag_uuid', 'extension_uuid'] : ['uuid'];
                if (! Schema::hasIndex($name, $primary, 'primary')) {
                    Schema::table($name, fn (Blueprint $table) => $table->primary($primary));
                }
            }

            // Replace the old constraint even if match_state was already added.
            $oldUnique = ['owner_type', 'owner_uuid', 'flag_uuid'];
            if (Schema::hasIndex('flag_overrides', $oldUnique, 'unique')) {
                Schema::table('flag_overrides', fn (Blueprint $table) => $table->dropUnique($oldUnique));
            }
            $this->index('flags', ['domain_uuid']);
            $this->index('flags', ['domain_uuid', 'flag_number'], true);
            $this->index('flag_rules', ['flag_uuid']);
            $this->index('flag_overrides', ['domain_uuid']);
            $this->index('flag_overrides', ['flag_uuid']);
            $this->index('flag_overrides', ['owner_type', 'owner_uuid']);
            $this->index('flag_overrides', ['owner_type', 'owner_uuid', 'flag_uuid', 'match_state'], true);

            foreach (['flag_rules', 'flag_extensions', 'flag_overrides'] as $name) {
                $exists = collect(Schema::getForeignKeys($name))->contains(
                    fn ($key) => $key['columns'] === ['flag_uuid'] && $key['foreign_table'] === 'flags'
                );
                if (! $exists) {
                    Schema::table($name, fn (Blueprint $table) => $table->foreign('flag_uuid')
                        ->references('uuid')->on('flags')->cascadeOnDelete());
                }
            }
        });
    }

    private function index(string $name, array $columns, bool $unique = false): void
    {
        if (! Schema::hasIndex($name, $columns, $unique ? 'unique' : null)) {
            Schema::table($name, fn (Blueprint $table) => $unique ? $table->unique($columns) : $table->index($columns));
        }
    }

    private function tables(): array
    {
        $uuid = fn ($table) => $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
        $timestamps = [
            'created_at' => fn ($table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn ($table) => $table->timestamp('updated_at')->nullable(),
        ];

        return [
            'flags' => [
                'uuid' => $uuid,
                'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                'name' => fn ($t) => $t->string('name'),
                'flag_number' => fn ($t) => $t->string('flag_number', 20),
                'type' => fn ($t) => $t->string('type', 20)->default('manual'),
                'timezone' => fn ($t) => $t->string('timezone')->nullable(),
                'active' => fn ($t) => $t->boolean('active')->default(false),
                'pin_number' => fn ($t) => $t->string('pin_number', 20)->nullable(),
                'blf_enabled' => fn ($t) => $t->boolean('blf_enabled')->default(false),
                'enabled' => fn ($t) => $t->boolean('enabled')->default(true),
                'description' => fn ($t) => $t->string('description')->nullable(),
                ...$timestamps,
            ],
            'flag_rules' => [
                'uuid' => $uuid,
                'flag_uuid' => fn ($t) => $t->uuid('flag_uuid'),
                'rule_type' => fn ($t) => $t->string('rule_type', 20),
                'weekdays' => fn ($t) => $t->string('weekdays', 20)->nullable(),
                'start_time' => fn ($t) => $t->time('start_time')->nullable(),
                'end_time' => fn ($t) => $t->time('end_time')->nullable(),
                'start_date' => fn ($t) => $t->date('start_date')->nullable(),
                'end_date' => fn ($t) => $t->date('end_date')->nullable(),
                ...$timestamps,
            ],
            'flag_extensions' => [
                'flag_uuid' => fn ($t) => $t->uuid('flag_uuid'),
                'extension_uuid' => fn ($t) => $t->uuid('extension_uuid'),
            ],
            'flag_overrides' => [
                'uuid' => $uuid,
                'domain_uuid' => fn ($t) => $t->uuid('domain_uuid'),
                'owner_type' => fn ($t) => $t->string('owner_type', 20),
                'owner_uuid' => fn ($t) => $t->uuid('owner_uuid'),
                'flag_uuid' => fn ($t) => $t->uuid('flag_uuid'),
                'match_state' => fn ($t) => $t->string('match_state', 10)->default('active'),
                'override_app' => fn ($t) => $t->string('override_app'),
                'override_data' => fn ($t) => $t->text('override_data')->nullable(),
                'sort_order' => fn ($t) => $t->integer('sort_order')->default(0),
                ...$timestamps,
            ],
        ];
    }

    public function down(): void
    {
        throw new RuntimeException('Flags may contain pre-existing data. Restore a matching backup to roll back this migration.');
    }
};
