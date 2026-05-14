<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v_ring_group_destinations', function (Blueprint $table) {
            $table->boolean('destination_ignore_voicemail')->default(false)->after('destination_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('v_ring_group_destinations', function (Blueprint $table) {
            $table->dropColumn('destination_ignore_voicemail');
        });
    }
};
