<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('v_extensions', 'emergency_location')) {
            Schema::table('v_extensions', fn (Blueprint $table) => $table->string('emergency_location', 60)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('v_extensions', fn (Blueprint $table) => $table->dropColumn('emergency_location'));
    }
};
