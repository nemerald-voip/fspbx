<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('v_devices', 'serial_number')) {
            Schema::table('v_devices', function (Blueprint $table) {
                $table->string('serial_number')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_devices', 'serial_number')) {
            Schema::table('v_devices', function (Blueprint $table) {
                $table->dropColumn('serial_number');
            });
        }
    }
};

