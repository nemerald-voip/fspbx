<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These tables were introduced with non-UUID primary keys before the
        // features reached production. Their disposable data is reset so the
        // final tables all have native UUID primary keys.
        Schema::dropIfExists('ldap_directory_user_group_assignments');
        Schema::dropIfExists('ldap_directory_group_mappings');
        Schema::dropIfExists('ldap_directory_group_members');
        Schema::dropIfExists('ai_provider_integrations');

        Schema::create('ldap_directory_group_members', function (Blueprint $table) {
            $this->uuidPrimaryKey($table, 'directory_group_member_uuid');
            $table->uuid('directory_group_uuid')->index();
            $table->uuid('directory_user_uuid')->index();
            $table->timestamps();

            $table->unique(
                ['directory_group_uuid', 'directory_user_uuid'],
                'ldap_group_member_unique'
            );
        });

        Schema::create('ldap_directory_group_mappings', function (Blueprint $table) {
            $this->uuidPrimaryKey($table, 'directory_group_mapping_uuid');
            $table->uuid('directory_uuid')->index();
            $table->uuid('directory_group_uuid')->index();
            $table->uuid('group_uuid')->index();
            $table->timestamps();

            $table->unique(
                ['directory_group_uuid', 'group_uuid'],
                'ldap_group_mapping_unique'
            );
        });

        Schema::create('ldap_directory_user_group_assignments', function (Blueprint $table) {
            $this->uuidPrimaryKey($table, 'directory_user_group_assignment_uuid');
            $table->uuid('directory_user_uuid')->index();
            $table->uuid('group_uuid')->index();
            $table->boolean('created_membership')->default(false);
            $table->timestamps();

            $table->unique(
                ['directory_user_uuid', 'group_uuid'],
                'ldap_user_group_assignment_unique'
            );
        });

        Schema::create('ai_provider_integrations', function (Blueprint $table) {
            $this->uuidPrimaryKey($table, 'ai_provider_integration_uuid');
            $table->string('provider', 50)->unique();
            $table->text('api_key')->nullable();
            $table->string('public_sip_host')->nullable();
            $table->json('provider_cidrs')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Rolling back must never restore the
        // former bigint or natural string primary keys.
    }

    private function uuidPrimaryKey(Blueprint $table, string $column): void
    {
        $table->uuid($column)
            ->primary()
            ->default(DB::raw('(uuid_generate_v4())'));
    }
};
