<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameAppTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nemerald_app_users', function (Blueprint $table) {
            $table->dropPrimary('nemerald_app_users_uuid');
        });
        Schema::rename('nemerald_app_users', 'mobile_app_users');
        Schema::table('mobile_app_users', function (Blueprint $table) {
            // $table->dropPrimary('nemerald_app_users_pkey');
            $table->renameColumn('nemerald_app_user_uuid', 'mobile_app_user_uuid');
            $table->primary('mobile_app_user_uuid');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mobile_app_users', function (Blueprint $table) {
            $table->dropPrimary('mobile_app_user_uuid');
        });
        Schema::rename('mobile_app_users', 'nemerald_app_users');

        Schema::table('nemerald_app_users', function(Blueprint $table) {
            $table->renameColumn('mobile_app_user_uuid', 'nemerald_app_user_uuid');
            $table->primary('nemerald_app_user_uuid');
        });
    }
}
