<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDomainGroupRelationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('domain_group_relations', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('domain_group_uuid');
            $table->foreign('domain_group_uuid')
                ->references('domain_group_uuid')
                ->on('domain_groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->uuid('domain_uuid');
            $table->foreign('domain_uuid')
                ->references('domain_uuid')
                ->on('v_domains')
                ->onUpdate('cascade')
                ->onDelete('cascade');
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
        Schema::dropIfExists('domain_group_relations');
    }
}
