<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('device_keys', 'key_area')) {
            Schema::table('device_keys', function (Blueprint $table) {
                $table->string('key_area', 50)
                    ->nullable()
                    ->default('main');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('device_keys', 'key_area')) {
            Schema::table('device_keys', function (Blueprint $table) {
                $table->dropColumn('key_area');
            });
        }
    }
};