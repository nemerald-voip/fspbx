<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('call_flow_groups')) {
            Schema::create('call_flow_groups', function (Blueprint $table) {
                $table->uuid('call_flow_group_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid');
                $table->string('call_flow_group_name');
                $table->string('call_flow_group_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_flow_groups');
    }
};
