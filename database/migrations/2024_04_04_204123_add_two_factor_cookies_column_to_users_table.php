<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_adv_fields', function (Blueprint $table) {
            $table->text('two_factor_cookies')
                  ->after('two_factor_recovery_codes')
                  ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_adv_fields', function (Blueprint $table) {
            $table->dropColumn('two_factor_cookies');
        });
    }
};
