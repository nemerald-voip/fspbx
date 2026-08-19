<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_provider_integrations')) {
            Schema::create('ai_provider_integrations', function (Blueprint $table) {
                $table->string('provider', 50)->primary();
                $table->text('api_key')->nullable();
                $table->string('public_sip_host')->nullable();
                $table->json('provider_cidrs')->nullable();
                $table->boolean('enabled')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->uuid('ai_agent_uuid')->primary();
                $table->uuid('domain_uuid')->index();
                $table->uuid('dialplan_uuid')->nullable()->unique();
                $table->string('name');
                $table->string('extension', 32);
                $table->string('provider', 50)->default('retell');
                $table->string('provider_phone_number')->nullable();
                $table->string('inbound_agent_id');
                $table->string('inbound_agent_name')->nullable();
                $table->string('outbound_agent_id')->nullable();
                $table->string('outbound_agent_name')->nullable();
                $table->string('recording_policy', 20)->default('inherit');
                $table->boolean('enabled')->default(true);
                $table->string('provisioning_status', 20)->default('provisioning');
                $table->text('provisioning_error')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'extension']);
                $table->index(['domain_uuid', 'enabled', 'provisioning_status']);
            });
        }

        if (! Schema::hasTable('ai_provider_tool_syncs')) {
            Schema::create('ai_provider_tool_syncs', function (Blueprint $table) {
                $table->uuid('ai_provider_tool_sync_uuid')->primary();
                $table->string('provider', 50);
                $table->string('provider_agent_id');
                $table->string('response_engine_type', 50)->nullable();
                $table->string('response_engine_id')->nullable();
                $table->unsignedInteger('response_engine_version')->nullable();
                $table->string('catalog_fingerprint', 64)->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('draft_agent_version')->nullable();
                $table->unsignedInteger('published_agent_version')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_agent_id']);
                $table->index(['provider', 'status']);
                $table->index('catalog_fingerprint');
            });
        }

        if (! Schema::hasTable('ai_tool_invocations')) {
            Schema::create('ai_tool_invocations', function (Blueprint $table) {
                $table->uuid('ai_tool_invocation_uuid')->primary();
                $table->uuid('domain_uuid')->index();
                $table->uuid('ai_agent_uuid')->index();
                $table->string('provider', 50);
                $table->string('provider_call_id');
                $table->string('tool_name', 64);
                $table->string('idempotency_key', 64);
                $table->json('request_payload')->nullable();
                $table->string('status', 20)->default('queued');
                $table->text('last_error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'idempotency_key']);
                $table->index(['domain_uuid', 'created_at']);
                $table->index(['ai_agent_uuid', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_invocations');
        Schema::dropIfExists('ai_provider_tool_syncs');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('ai_provider_integrations');
    }
};
