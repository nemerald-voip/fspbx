<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('v_ai_agents')) {
            Schema::create('v_ai_agents', function (Blueprint $table) {
                $table->uuid('ai_agent_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid');
                $table->uuid('dialplan_uuid')->nullable();
                $table->string('agent_name', 100);
                $table->string('agent_extension', 10);
                $table->string('elevenlabs_agent_id', 255)->nullable();
                $table->string('elevenlabs_phone_number_id', 255)->nullable();
                $table->text('system_prompt')->nullable();
                $table->text('first_message')->nullable();
                $table->string('voice_id', 255)->nullable();
                $table->string('language', 20)->default('en');
                $table->string('agent_enabled', 10)->default('true');
                $table->string('description', 255)->nullable();
                $table->timestamp('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestamp('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v_ai_agents');
    }
};
