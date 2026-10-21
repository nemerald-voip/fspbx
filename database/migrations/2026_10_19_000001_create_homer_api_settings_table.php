<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the connection saved on development installs that used the
        // initial integer key. Fresh installations only create the UUID table.
        if (Schema::hasTable('homer_api_settings')) {
            if (Schema::hasColumn('homer_api_settings', 'id')) {
                DB::transaction(function () {
                    if (DB::table('homer_api_settings')->where('id', '!=', 1)->exists()) {
                        throw new RuntimeException('Unexpected HOMER settings rows; migration stopped without changing them.');
                    }
                    DB::statement("ALTER TABLE homer_api_settings ALTER COLUMN id TYPE uuid USING 'c0d4ab13-0a82-4f50-a5be-2f5d621d374e'::uuid");
                    DB::statement('ALTER TABLE homer_api_settings RENAME COLUMN id TO homer_api_setting_uuid');
                });
            }
            return;
        }

        Schema::create('homer_api_settings', function (Blueprint $table) {
            $table->uuid('homer_api_setting_uuid')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('base_url', 2048)->nullable();
            $table->string('auth_type', 20)->default('auth_token');
            $table->text('api_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homer_api_settings');
    }
};
