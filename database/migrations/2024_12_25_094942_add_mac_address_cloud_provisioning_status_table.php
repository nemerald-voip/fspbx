<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('cloud_provisioning_status', function (Blueprint $table) {
            if (!Schema::hasColumn('cloud_provisioning_status', 'device_address')) {
                $table->text('device_address')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cloud_provisioning_status', function (Blueprint $table) {
             if (Schema::hasColumn('cloud_provisioning_status', 'device_address')) {
                 $table->dropColumn('device_address');
             }
        });
    }
};
