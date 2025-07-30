<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('v_users', 'extension_uuid')) {
            Schema::table('v_users', function (Blueprint $table) {
                $table->uuid('extension_uuid')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_users', 'extension_uuid')) {
            Schema::table('v_users', function (Blueprint $table) {
                $table->dropColumn('extension_uuid');
            });
        }
    }
};
