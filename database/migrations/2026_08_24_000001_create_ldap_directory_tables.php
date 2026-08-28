<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ldap_directories')) {
            Schema::create('ldap_directories', function (Blueprint $table) {
                $table->uuid('directory_uuid')->primary();
                $table->uuid('domain_uuid')->index();
                $table->string('type', 32)->default('active_directory');
                $table->string('name');
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('priority')->default(100);
                $table->unsignedInteger('sync_interval_minutes')->default(60);

                $table->string('secure_connection', 16)->default('ldaps');
                $table->text('hosts');
                $table->unsignedSmallInteger('port')->default(636);
                $table->string('bind_username');
                $table->text('bind_password')->nullable();
                $table->string('ad_domain');
                $table->text('base_dn');

                $table->string('create_missing_extensions', 32)->default('none');
                $table->boolean('manage_groups_locally')->default(false);
                $table->string('common_name_attribute', 128)->default('cn');
                $table->string('description_attribute', 128)->default('description');
                $table->string('unique_identifier_attribute', 128)->default('objectGUID');

                $table->text('user_dn')->nullable();
                $table->string('user_object_class', 128)->default('user');
                $table->text('user_object_filter')->default('(&(objectCategory=Person)(sAMAccountName=*))');
                $table->string('user_name_attribute', 128)->default('sAMAccountName');
                $table->string('user_first_name_attribute', 128)->default('givenName');
                $table->string('user_last_name_attribute', 128)->default('sn');
                $table->string('user_display_name_attribute', 128)->default('displayName');
                $table->string('user_group_attribute', 128)->default('memberOf');
                $table->string('user_email_attribute', 128)->default('mail');
                $table->string('user_title_attribute', 128)->nullable()->default('title');
                $table->string('user_company_attribute', 128)->nullable()->default('company');
                $table->string('user_department_attribute', 128)->nullable()->default('department');
                $table->string('user_home_phone_attribute', 128)->nullable()->default('homePhone');
                $table->string('user_work_phone_attribute', 128)->nullable()->default('telephoneNumber');
                $table->string('user_cell_phone_attribute', 128)->nullable()->default('mobile');
                $table->string('user_fax_attribute', 128)->nullable()->default('facsimileTelephoneNumber');
                $table->string('user_extension_attribute', 128)->nullable()->default('ipPhone');

                $table->text('group_dn')->nullable();
                $table->string('group_object_class', 128)->default('group');
                $table->text('group_object_filter')->default('(objectCategory=Group)');
                $table->string('group_members_attribute', 128)->default('member');

                $table->string('connection_status', 32)->default('not_tested');
                $table->text('connection_message')->nullable();
                $table->timestamp('connection_tested_at')->nullable();
                $table->string('last_sync_status', 32)->nullable();
                $table->text('last_sync_message')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('next_sync_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['domain_uuid', 'name']);
                $table->index(['domain_uuid', 'enabled']);
                $table->index(['enabled', 'next_sync_at']);
            });
        }

        if (! Schema::hasTable('ldap_directory_users')) {
            Schema::create('ldap_directory_users', function (Blueprint $table) {
                $table->uuid('directory_user_uuid')->primary();
                $table->uuid('directory_uuid')->index();
                $table->uuid('domain_uuid')->index();
                $table->uuid('user_uuid')->nullable()->index();
                $table->string('external_id', 128);
                $table->text('distinguished_name')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('extension')->nullable();
                $table->boolean('remote_enabled')->default(true);
                $table->json('profile')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['directory_uuid', 'external_id']);
                $table->unique(['directory_uuid', 'user_uuid']);
                $table->index(['directory_uuid', 'last_seen_at']);
            });
        }

        if (! Schema::hasTable('ldap_directory_groups')) {
            Schema::create('ldap_directory_groups', function (Blueprint $table) {
                $table->uuid('directory_group_uuid')->primary();
                $table->uuid('directory_uuid')->index();
                $table->uuid('domain_uuid')->index();
                $table->string('external_id', 128);
                $table->text('distinguished_name')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('primary_group_token', 64)->nullable();
                $table->boolean('local')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['directory_uuid', 'external_id']);
                $table->index(['directory_uuid', 'last_seen_at']);
            });
        }

        if (! Schema::hasTable('ldap_directory_group_members')) {
            Schema::create('ldap_directory_group_members', function (Blueprint $table) {
                $table->id();
                $table->uuid('directory_group_uuid')->index();
                $table->uuid('directory_user_uuid')->index();
                $table->timestamps();

                $table->unique(['directory_group_uuid', 'directory_user_uuid'], 'ldap_group_member_unique');
            });
        }

        if (! Schema::hasTable('ldap_directory_group_mappings')) {
            Schema::create('ldap_directory_group_mappings', function (Blueprint $table) {
                $table->id();
                $table->uuid('directory_uuid')->index();
                $table->uuid('directory_group_uuid')->index();
                $table->uuid('group_uuid')->index();
                $table->timestamps();

                $table->unique(['directory_group_uuid', 'group_uuid'], 'ldap_group_mapping_unique');
            });
        }

        if (! Schema::hasTable('ldap_directory_user_group_assignments')) {
            Schema::create('ldap_directory_user_group_assignments', function (Blueprint $table) {
                $table->id();
                $table->uuid('directory_user_uuid')->index();
                $table->uuid('group_uuid')->index();
                $table->boolean('created_membership')->default(false);
                $table->timestamps();

                $table->unique(['directory_user_uuid', 'group_uuid'], 'ldap_user_group_assignment_unique');
            });
        }

        if (! Schema::hasTable('ldap_sync_runs')) {
            Schema::create('ldap_sync_runs', function (Blueprint $table) {
                $table->uuid('sync_run_uuid')->primary();
                $table->uuid('directory_uuid')->index();
                $table->uuid('domain_uuid')->index();
                $table->string('status', 32)->default('running');
                $table->boolean('dry_run')->default(false);
                $table->unsignedInteger('users_seen')->default(0);
                $table->unsignedInteger('users_created')->default(0);
                $table->unsignedInteger('users_updated')->default(0);
                $table->unsignedInteger('users_disabled')->default(0);
                $table->unsignedInteger('users_conflicted')->default(0);
                $table->unsignedInteger('groups_seen')->default(0);
                $table->json('messages')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ldap_sync_runs');
        Schema::dropIfExists('ldap_directory_user_group_assignments');
        Schema::dropIfExists('ldap_directory_group_mappings');
        Schema::dropIfExists('ldap_directory_group_members');
        Schema::dropIfExists('ldap_directory_groups');
        Schema::dropIfExists('ldap_directory_users');
        Schema::dropIfExists('ldap_directories');
    }
};
