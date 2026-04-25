<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v_extensions', function (Blueprint $table) {
            $table->string('ring_target')->default('both')->after('do_not_disturb');
        });
    }

    public function down(): void
    {
        Schema::table('v_extensions', function (Blueprint $table) {
            $table->dropColumn('ring_target');
        });
    }
};
