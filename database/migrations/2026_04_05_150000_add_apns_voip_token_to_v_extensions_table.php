<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v_extensions', function (Blueprint $table) {
            $table->string('apns_voip_token')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('v_extensions', function (Blueprint $table) {
            $table->dropColumn('apns_voip_token');
        });
    }
};
