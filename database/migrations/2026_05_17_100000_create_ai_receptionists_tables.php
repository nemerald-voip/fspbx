<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_receptionist_settings')) {
            Schema::create('ai_receptionist_settings', function (Blueprint $table) {
                $table->uuid('setting_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->nullable()->index();
                $table->string('default_engine')->nullable();
                $table->string('agent_runtime')->nullable()->index();
                $table->text('livekit_url')->nullable();
                $table->text('livekit_api_key')->nullable();
                $table->text('livekit_api_secret')->nullable();
                $table->json('provider_config')->nullable();
                $table->boolean('enabled')->default(false)->index();
                $table->timestamps();

                $table->unique(['domain_uuid']);
            });
        }

        if (! Schema::hasTable('ai_receptionists')) {
            Schema::create('ai_receptionists', function (Blueprint $table) {
                $table->uuid('ai_receptionist_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('dialplan_uuid')->nullable()->index();
                $table->string('name');
                $table->string('extension')->index();
                $table->text('system_prompt')->nullable();
                $table->text('initial_message')->nullable();
                $table->text('fallback_type')->nullable();
                $table->text('fallback_target')->nullable();
                $table->text('fallback_label')->nullable();
                $table->unsignedInteger('max_duration_seconds')->default(900);
                $table->unsignedInteger('user_silence_checkin_seconds')->default(15);
                $table->unsignedInteger('user_idle_timeout_seconds')->default(60);
                $table->boolean('allow_interruptions')->default(true);
                $table->decimal('min_interruption_duration', 4, 2)->default(0.50);
                $table->boolean('transcript_enabled')->default(true);
                $table->boolean('tool_access_enabled')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'extension']);
            });
        }

        if (! Schema::hasTable('ai_receptionist_tools')) {
            Schema::create('ai_receptionist_tools', function (Blueprint $table) {
                $table->uuid('tool_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('ai_receptionist_uuid')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('method')->default('POST');
                $table->text('url');
                $table->json('headers')->nullable();
                $table->json('request_schema')->nullable();
                $table->unsignedInteger('timeout_seconds')->default(10);
                $table->boolean('enabled')->default(true)->index();
                $table->timestamps();

                $table->unique(['domain_uuid', 'name']);
            });
        }

        if (! Schema::hasTable('ai_receptionist_sessions')) {
            Schema::create('ai_receptionist_sessions', function (Blueprint $table) {
                $table->uuid('session_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('ai_receptionist_uuid')->index();
                $table->uuid('setting_uuid')->nullable()->index();
                $table->string('engine');
                $table->text('status')->default('started')->index();
                $table->text('freeswitch_uuid')->nullable()->index();
                $table->text('livekit_room')->nullable()->index();
                $table->text('livekit_participant')->nullable();
                $table->text('caller_id_name')->nullable();
                $table->text('caller_id_number')->nullable();
                $table->text('destination_number')->nullable();
                $table->text('transfer_type')->nullable();
                $table->text('transfer_target')->nullable();
                $table->text('transfer_label')->nullable();
                $table->text('error_message')->nullable();
                $table->longText('transcript')->nullable();
                $table->json('summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_receptionist_tool_runs')) {
            Schema::create('ai_receptionist_tool_runs', function (Blueprint $table) {
                $table->uuid('tool_run_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('session_uuid')->index();
                $table->uuid('tool_uuid')->nullable()->index();
                $table->string('tool_name');
                $table->text('status')->default('started')->index();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_receptionist_tool_runs');
        Schema::dropIfExists('ai_receptionist_sessions');
        Schema::dropIfExists('ai_receptionist_tools');
        Schema::dropIfExists('ai_receptionists');
        Schema::dropIfExists('ai_receptionist_settings');
    }
};
