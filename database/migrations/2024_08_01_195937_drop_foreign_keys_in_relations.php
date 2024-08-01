<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropForeignKeysInRelations extends Migration
{
    public function up()
    {
        // For domain_group_relations table
        Schema::table('domain_group_relations', function (Blueprint $table) {
            DB::statement('ALTER TABLE domain_group_relations DROP CONSTRAINT IF EXISTS domain_group_relations_domain_group_uuid_foreign');
            DB::statement('ALTER TABLE domain_group_relations DROP CONSTRAINT IF EXISTS domain_group_relations_domain_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS domain_group_relations_domain_group_uuid_index');
            DB::statement('DROP INDEX IF EXISTS domain_group_relations_domain_uuid_index');
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_group_uuid TYPE TEXT USING domain_group_uuid::TEXT');
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_uuid TYPE TEXT USING domain_uuid::TEXT');
        });

        // For user_domain_group_permissions table
        Schema::table('user_domain_group_permissions', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_group_permissions DROP CONSTRAINT IF EXISTS user_domain_group_permissions_user_uuid_foreign');
            DB::statement('ALTER TABLE user_domain_group_permissions DROP CONSTRAINT IF EXISTS user_domain_group_permissions_domain_group_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS user_domain_group_permissions_user_uuid_index');
            DB::statement('DROP INDEX IF EXISTS user_domain_group_permissions_domain_group_uuid_index');
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN domain_group_uuid TYPE TEXT USING domain_group_uuid::TEXT');
        });

        // For user_domain_permission table
        Schema::table('user_domain_permission', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_permission DROP CONSTRAINT IF EXISTS user_domain_permission_user_uuid_foreign');
            DB::statement('ALTER TABLE user_domain_permission DROP CONSTRAINT IF EXISTS user_domain_permission_domain_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS user_domain_permission_user_uuid_index');
            DB::statement('DROP INDEX IF EXISTS user_domain_permission_domain_uuid_index');
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN domain_uuid TYPE TEXT USING domain_uuid::TEXT');
        });

        // For fax_allowed_emails table
        Schema::table('fax_allowed_emails', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_emails DROP CONSTRAINT IF EXISTS fax_allowed_emails_fax_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS fax_allowed_emails_fax_uuid_index');
            DB::statement('ALTER TABLE fax_allowed_emails ALTER COLUMN fax_uuid TYPE TEXT USING fax_uuid::TEXT');
        });

        // For fax_allowed_domain_names table
        Schema::table('fax_allowed_domain_names', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_domain_names DROP CONSTRAINT IF EXISTS fax_allowed_domain_names_fax_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS fax_allowed_domain_names_fax_uuid_index');
            DB::statement('ALTER TABLE fax_allowed_domain_names ALTER COLUMN fax_uuid TYPE TEXT USING fax_uuid::TEXT');
        });

        // For users_adv_fields table
        Schema::table('users_adv_fields', function (Blueprint $table) {
            DB::statement('ALTER TABLE users_adv_fields DROP CONSTRAINT IF EXISTS user_name_info_user_uuid_foreign');
            DB::statement('DROP INDEX IF EXISTS users_adv_fields_user_uuid_index');
            DB::statement('ALTER TABLE users_adv_fields ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
        });
    }

    public function down()
    {
        // Revert changes for domain_group_relations table
        Schema::table('domain_group_relations', function (Blueprint $table) {
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_group_uuid TYPE UUID USING domain_group_uuid::UUID');
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_uuid TYPE UUID USING domain_uuid::UUID');
            $table->foreign('domain_group_uuid')
                ->references('domain_group_uuid')
                ->on('domain_groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('domain_uuid')
                ->references('domain_uuid')
                ->on('v_domains')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // Revert changes for user_domain_group_permissions table
        Schema::table('user_domain_group_permissions', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN domain_group_uuid TYPE UUID USING domain_group_uuid::UUID');
            $table->foreign('user_uuid')
                ->references('user_uuid')
                ->on('v_users')
                ->onDelete('cascade');
            $table->foreign('domain_group_uuid')
                ->references('domain_group_uuid')
                ->on('domain_groups')
                ->onDelete('cascade');
        });

        // Revert changes for user_domain_permission table
        Schema::table('user_domain_permission', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN domain_uuid TYPE UUID USING domain_uuid::UUID');
            $table->foreign('user_uuid')
                ->references('user_uuid')
                ->on('v_users')
                ->onDelete('cascade');
            $table->foreign('domain_uuid')
                ->references('domain_uuid')
                ->on('v_domains')
                ->onDelete('cascade');
        });

        // Revert changes for fax_allowed_emails table
        Schema::table('fax_allowed_emails', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_emails ALTER COLUMN fax_uuid TYPE UUID USING fax_uuid::UUID');
            $table->foreign('fax_uuid')
                ->references('fax_uuid')
                ->on('v_fax')
                ->onDelete('cascade');
        });

        // Revert changes for fax_allowed_domain_names table
        Schema::table('fax_allowed_domain_names', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_domain_names ALTER COLUMN fax_uuid TYPE UUID USING fax_uuid::UUID');
            $table->foreign('fax_uuid')
                ->references('fax_uuid')
                ->on('v_fax')
                ->onDelete('cascade');
        });

        // Revert changes for users_adv_fields table
        Schema::table('users_adv_fields', function (Blueprint $table) {
            DB::statement('ALTER TABLE users_adv_fields ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
            $table->foreign('user_uuid')
                ->references('user_uuid')
                ->on('v_users')
                ->onDelete('cascade');
        });
    }
}
