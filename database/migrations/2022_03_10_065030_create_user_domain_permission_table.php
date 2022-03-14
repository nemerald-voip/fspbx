<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserDomainPermissionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_domain_permission', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_uuid');
            $table->uuid('domain_uuid');
            $table->timestamps();
            $table->foreign('user_uuid') ->references('user_uuid')->on('v_users')->onDelete('cascade');
            $table->foreign('domain_uuid') ->references('domain_uuid')->on('v_domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_domain_permission');
    }
}
