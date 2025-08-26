<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add columns / indexes if missing
        Schema::table('v_devices', function (Blueprint $t) {
            if (!Schema::hasColumn('v_devices', 'device_template_uuid')) {
                $t->uuid('device_template_uuid')->nullable();
                // name the index explicitly so we can drop it later
                $t->index('device_template_uuid', 'v_devices_device_template_uuid_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('v_devices', function (Blueprint $t) {
            // drop index before column
            try { $t->dropIndex('v_devices_device_template_uuid_idx'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('v_devices', 'device_template_uuid')) {
                $t->dropColumn('device_template_uuid');
            }
        });
    }
};
