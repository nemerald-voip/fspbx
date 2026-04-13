<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('messages', 'delivery_meta')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->jsonb('delivery_meta')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'delivery_meta')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('delivery_meta');
            });
        }
    }
};