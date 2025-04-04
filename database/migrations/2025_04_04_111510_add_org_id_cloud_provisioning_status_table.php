<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('cloud_provisioning_status', 'ztp_profile_id')) {
            Schema::table('cloud_provisioning_status', function (Blueprint $table) {
                $table->string('ztp_profile_id')->nullable()->after('device_address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cloud_provisioning_status', 'ztp_profile_id')) {
            Schema::table('cloud_provisioning_status', function (Blueprint $table) {
                $table->dropColumn('ztp_profile_id');
            });
        }
    }
};
