<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNemeraldAppUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nemerald_app_users', function (Blueprint $table) {
            $table->uuid('nemerald_app_user_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('extension_uuid');
            $table->uuid('domain_uuid');
            $table->string('org_id');
            $table->string('conn_id');
            $table->string('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nemerald_app_users');
    }
}
