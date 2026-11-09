<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Earlier development migrations may already have created these tables.
        if (!Schema::hasTable('message_api_requests')) {
            Schema::create('message_api_requests', function (Blueprint $table) {
                $table->uuid('message_api_request_uuid')->primary();
                $table->uuid('domain_uuid');
                $table->uuid('user_uuid');
                $table->string('operation', 20);
                $table->string('key_hash', 64);
                $table->string('fingerprint', 64);
                $table->uuid('message_uuid');
                $table->jsonb('response');
                $table->timestampTz('expires_at');
                $table->timestampsTz();
                $table->unique(['domain_uuid', 'user_uuid', 'operation', 'key_hash'], 'message_api_request_key');
                $table->index('created_at');
            });
        }
        if (!Schema::hasTable('outbound_message_webhook_events')) {
            Schema::create('outbound_message_webhook_events', function (Blueprint $table) {
                $table->uuid('outbound_message_webhook_event_uuid')->primary();
                $table->uuid('domain_uuid')->index();
                $table->uuid('message_uuid');
                $table->string('event_type', 40);
                $table->string('fingerprint', 64);
                $table->text('endpoint_url');
                $table->jsonb('payload');
                $table->jsonb('media_items');
                $table->boolean('prepared')->default(false);
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->timestampTz('attempted_at')->nullable();
                $table->timestampsTz();
                $table->unique(['message_uuid', 'event_type', 'fingerprint'], 'outbound_message_event_unique');
                $table->index('created_at');
            });
        }
        if (!Schema::hasTable('outbound_webhook_logs')) {
            Schema::create('outbound_webhook_logs', function (Blueprint $table) {
                $table->uuid('outbound_webhook_log_uuid')->primary();
                $table->string('delivery_key', 64)->unique();
                $table->uuid('domain_uuid')->nullable();
                $table->string('event_id');
                $table->string('event_type');
                $table->string('related_id')->nullable();
                $table->string('http_method', 10);
                $table->text('endpoint_url');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('error')->nullable();
                $table->jsonb('attempt_history');
                $table->timestampTz('attempted_at')->nullable();
                $table->timestampsTz();
                $table->index(['domain_uuid', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhook_logs');
        Schema::dropIfExists('outbound_message_webhook_events');
        Schema::dropIfExists('message_api_requests');
    }
};
