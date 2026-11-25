<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_faxes', function (Blueprint $table) {
            $table->string('sending_host', 64)->nullable();
            $table->timestampTz('channel_missing_since')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outbound_faxes', function (Blueprint $table) {
            $table->dropColumn(['sending_host', 'channel_missing_since']);
        });
    }
};
