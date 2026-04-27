<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('v_call_flows', 'call_flow_group')) {
            Schema::table('v_call_flows', function (Blueprint $table) {
                $table->string('call_flow_group')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_call_flows', 'call_flow_group')) {
            Schema::table('v_call_flows', function (Blueprint $table) {
                $table->dropColumn('call_flow_group');
            });
        }
    }
};
