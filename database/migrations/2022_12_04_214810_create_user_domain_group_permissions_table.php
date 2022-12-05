<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserDomainGroupPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_domain_group_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_uuid');
            $table->uuid('domain_group_uuid');
            $table->timestamps();
            $table->foreign('user_uuid') ->references('user_uuid')->on('v_users')->onDelete('cascade');
            $table->foreign('domain_group_uuid') ->references('domain_group_uuid')->on('domain_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_domain_group_permissions');
    }
}
