<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCloudProvisioningStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cloud_provisioning_status')) {
            Schema::create('cloud_provisioning_status', function (Blueprint $table) {
                $table->uuid('device_uuid')->unique();
                $table->string('provider', 24);
                $table->string('status', 24);
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
        Schema::dropIfExists('cloud_provisioning_status');
    }
}
