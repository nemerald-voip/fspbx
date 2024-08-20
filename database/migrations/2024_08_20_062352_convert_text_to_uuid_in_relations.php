<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertTextToUuidInRelations extends Migration
{
    public function up()
    {
        // For domain_group_relations table
        Schema::table('domain_group_relations', function (Blueprint $table) {
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_group_uuid TYPE UUID USING domain_group_uuid::UUID');
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_uuid TYPE UUID USING domain_uuid::UUID');
        });

        // For user_domain_group_permissions table
        Schema::table('user_domain_group_permissions', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN domain_group_uuid TYPE UUID USING domain_group_uuid::UUID');
        });

        // For user_domain_permission table
        Schema::table('user_domain_permission', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN domain_uuid TYPE UUID USING domain_uuid::UUID');
        });

        // For fax_allowed_emails table
        Schema::table('fax_allowed_emails', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_emails ALTER COLUMN fax_uuid TYPE UUID USING fax_uuid::UUID');
        });

        // For fax_allowed_domain_names table
        Schema::table('fax_allowed_domain_names', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_domain_names ALTER COLUMN fax_uuid TYPE UUID USING fax_uuid::UUID');
        });

        // For users_adv_fields table
        Schema::table('users_adv_fields', function (Blueprint $table) {
            DB::statement('ALTER TABLE users_adv_fields ALTER COLUMN user_uuid TYPE UUID USING user_uuid::UUID');
        });
    }

    public function down()
    {
        // Revert changes for domain_group_relations table
        Schema::table('domain_group_relations', function (Blueprint $table) {
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_group_uuid TYPE TEXT USING domain_group_uuid::TEXT');
            DB::statement('ALTER TABLE domain_group_relations ALTER COLUMN domain_uuid TYPE TEXT USING domain_uuid::TEXT');
        });

        // Revert changes for user_domain_group_permissions table
        Schema::table('user_domain_group_permissions', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
            DB::statement('ALTER TABLE user_domain_group_permissions ALTER COLUMN domain_group_uuid TYPE TEXT USING domain_group_uuid::TEXT');
        });

        // Revert changes for user_domain_permission table
        Schema::table('user_domain_permission', function (Blueprint $table) {
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
            DB::statement('ALTER TABLE user_domain_permission ALTER COLUMN domain_uuid TYPE TEXT USING domain_uuid::TEXT');
        });

        // Revert changes for fax_allowed_emails table
        Schema::table('fax_allowed_emails', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_emails ALTER COLUMN fax_uuid TYPE TEXT USING fax_uuid::TEXT');
        });

        // Revert changes for fax_allowed_domain_names table
        Schema::table('fax_allowed_domain_names', function (Blueprint $table) {
            DB::statement('ALTER TABLE fax_allowed_domain_names ALTER COLUMN fax_uuid TYPE TEXT USING fax_uuid::TEXT');
        });

        // Revert changes for users_adv_fields table
        Schema::table('users_adv_fields', function (Blueprint $table) {
            DB::statement('ALTER TABLE users_adv_fields ALTER COLUMN user_uuid TYPE TEXT USING user_uuid::TEXT');
        });
    }
}
