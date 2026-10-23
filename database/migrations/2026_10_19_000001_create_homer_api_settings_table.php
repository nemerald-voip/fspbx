<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homer_api_settings')) {
            Schema::create('homer_api_settings', function (Blueprint $table) {
                $table->uuid('homer_api_setting_uuid')->primary();
                $table->boolean('enabled')->default(false);
                $table->string('base_url', 2048)->nullable();
                $table->string('auth_type', 20)->default('auth_token');
                $table->text('api_token')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homer_api_settings');
    }
};
