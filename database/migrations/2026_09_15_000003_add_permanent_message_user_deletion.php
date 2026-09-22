<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('message_user_hides', function (Blueprint $table) {
            // Existing hides retain their original, non-destructive behavior.
            $table->boolean('history_deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('message_user_hides', fn (Blueprint $table) => $table->dropColumn('history_deleted'));
    }
};
