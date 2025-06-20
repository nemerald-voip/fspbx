<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('emergency_call_members')) {
            Schema::create('emergency_call_members', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('emergency_call_uuid');
                $table->uuid('domain_uuid');
                $table->uuid('extension_uuid');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_call_members');
    }
};
