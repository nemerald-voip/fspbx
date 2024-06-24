<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extension_advanced_settings', function (Blueprint $table) {
            $table->uuid('setting_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('extension_uuid')->nullable();
            $table->boolean('suspended')->default(false);
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extension_advanced_settings');
    }
};
