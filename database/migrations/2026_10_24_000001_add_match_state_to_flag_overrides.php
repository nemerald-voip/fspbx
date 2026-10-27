<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('flag_overrides', 'match_state')) {
            return;
        }

        Schema::table('flag_overrides', function (Blueprint $table) {
            // active: applies while the flag is active; inactive: while it is not.
            $table->string('match_state', 10)->default('active');
        });

        // A flag may now appear twice on one owner: once for each state.
        Schema::table('flag_overrides', function (Blueprint $table) {
            $table->dropUnique(['owner_type', 'owner_uuid', 'flag_uuid']);
            $table->unique(['owner_type', 'owner_uuid', 'flag_uuid', 'match_state']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('flag_overrides', 'match_state')) {
            return;
        }

        Schema::table('flag_overrides', function (Blueprint $table) {
            $table->dropUnique(['owner_type', 'owner_uuid', 'flag_uuid', 'match_state']);
            $table->dropColumn('match_state');
        });
    }
};
