<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v_user_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('v_user_logs', 'email')) {
                $table->string('email')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('v_user_logs', function (Blueprint $table) {
            if (Schema::hasColumn('v_user_logs', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
