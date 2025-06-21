<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('device_cloud_provisioning')) {
            Schema::create('device_cloud_provisioning', function (Blueprint $table) {
                $table->uuid('uuid')->unique();
                $table->uuid('device_uuid');
                $table->text('provider')->nullable();
                $table->text('status')->nullable();
                $table->text('error')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_cloud_provisioning');
    }
};
