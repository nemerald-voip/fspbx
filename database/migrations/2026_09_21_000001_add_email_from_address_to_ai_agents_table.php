<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_agents', 'email_from_address')) {
            return;
        }

        Schema::table('ai_agents', function (Blueprint $table) {
            $table->string('email_from_address', 254)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ai_agents', 'email_from_address')) {
            return;
        }

        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn('email_from_address');
        });
    }
};
