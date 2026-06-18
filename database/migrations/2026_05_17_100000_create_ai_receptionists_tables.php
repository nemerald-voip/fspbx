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
                $table->string('openai_voice')->default('marin');
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
                $table->text('openai_call_id')->nullable()->index();
                $table->text('sip_call_id')->nullable()->index();
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

        if (! Schema::hasTable('ai_receptionist_routes')) {
            Schema::create('ai_receptionist_routes', function (Blueprint $table) {
                $table->uuid('route_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('ai_receptionist_uuid')->index();
                $table->string('name');
                $table->json('match_phrases')->nullable();
                $table->json('collected_fields')->nullable();
                $table->string('action_type')->default('transfer')->index();
                $table->string('transfer_type')->nullable()->index();
                $table->string('destination_type')->nullable();
                $table->text('destination_target')->nullable();
                $table->text('destination_label')->nullable();
                $table->text('email_to')->nullable();
                $table->text('email_subject')->nullable();
                $table->text('email_instructions')->nullable();
                $table->boolean('notify_on_failed_warm_transfer')->default(false);
                $table->text('failed_transfer_email_to')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_receptionist_warm_transfers')) {
            Schema::create('ai_receptionist_warm_transfers', function (Blueprint $table) {
                $table->uuid('warm_transfer_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('session_uuid')->index();
                $table->uuid('route_uuid')->nullable()->index();
                $table->text('status')->default('started')->index();
                $table->text('caller_uuid')->nullable()->index();
                $table->text('openai_uuid')->nullable()->index();
                $table->text('consult_openai_call_id')->nullable()->index();
                $table->text('consult_sip_call_id')->nullable()->index();
                $table->text('consult_freeswitch_uuid')->nullable()->index();
                $table->text('recipient_uuid')->nullable()->index();
                $table->text('destination_type')->nullable();
                $table->text('destination_target')->nullable();
                $table->text('destination_label')->nullable();
                $table->longText('handoff_summary')->nullable();
                $table->string('decision')->nullable()->index();
                $table->longText('recipient_response')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
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
        Schema::dropIfExists('ai_receptionist_warm_transfers');
        Schema::dropIfExists('ai_receptionist_routes');
        Schema::dropIfExists('ai_receptionist_sessions');
        Schema::dropIfExists('ai_receptionist_tools');
        Schema::dropIfExists('ai_receptionists');
        Schema::dropIfExists('ai_receptionist_settings');
    }
};
