<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDomainGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('domain_groups', function (Blueprint $table) {
            $table->uuid('domain_group_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('group_name');
            // $table->uuid('domain_uuid');
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
        Schema::dropIfExists('domain_groups');
    }
}
