<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_center_changes', function (Blueprint $t) {
            $t->uuid('contact_center_change_uuid')->primary();
            $t->string('source_node', 32);
            $t->uuid('domain_uuid')->nullable()->index();
            $t->string('kind', 64);
            $t->uuid('entity_uuid');
            $t->index(['kind', 'entity_uuid']);
            $t->jsonb('previous')->nullable();
            $t->timestampTz('created_at')->useCurrent()->index();
        });
        Schema::create('contact_center_status_events', function (Blueprint $t) {
            $t->uuid('contact_center_status_event_uuid')->primary();
            $t->uuid('domain_uuid');
            $t->uuid('agent_uuid')->index();
            $t->string('source_node', 32);
            $t->string('status', 32);
            $t->string('source', 32);
            $t->jsonb('clock');
            $t->timestampTz('created_at')->useCurrent();
        });
        Schema::create('contact_center_node_state', function (Blueprint $t) {
            $t->uuid('contact_center_node_state_uuid')->primary();
            $t->string('node_id', 32);
            $t->string('object_key', 160);
            $t->string('core_uuid', 36)->nullable();
            $t->string('fingerprint', 64)->nullable();
            $t->string('status', 32)->default('pending');
            $t->text('error')->nullable();
            $t->jsonb('data')->nullable();
            $t->timestampTz('updated_at')->nullable();
            $t->timestampTz('last_success_at')->nullable();
            $t->unique(['node_id', 'object_key']);
        });
        Schema::create('contact_center_callback_settings', function (Blueprint $t) {
            $t->uuid('contact_center_callback_setting_uuid')->primary();
            $t->uuid('queue_uuid')->unique();
            $t->uuid('domain_uuid')->index();
            $t->boolean('enabled')->default(false);
            $t->string('introduction')->nullable();
            $t->string('instruction')->nullable();
            $t->unsignedInteger('offer_after_seconds')->default(90);
            $t->string('offer_key', 1)->default('9');
            $t->string('timezone', 64)->default('UTC');
            $t->string('closing_time', 5)->default('17:00');
            $t->unsignedInteger('max_attempts')->default(3);
            $t->unsignedInteger('retry_seconds')->default(600);
        });
        Schema::create('contact_center_callbacks', function (Blueprint $t) {
            $t->uuid('contact_center_callback_uuid')->primary();
            $t->uuid('domain_uuid')->index();
            $t->uuid('queue_uuid')->index();
            $t->uuid('original_call_uuid')->unique();
            $t->string('source_node', 32);
            $t->string('number', 32);
            $t->string('name_path', 512);
            $t->string('name_sha256', 64);
            $t->unsignedInteger('name_bytes');
            $t->timestampTz('media_ready_at')->nullable();
            $t->bigInteger('joined_epoch');
            $t->integer('base_score')->default(0);
            $t->string('status', 32)->default('waiting')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->uuid('current_attempt_uuid')->nullable();
            $t->timestampTz('next_attempt_at')->index();
            $t->timestampTz('expires_at')->index();
            $t->timestampTz('cleanup_requested_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestampsTz();
        });
        Schema::create('contact_center_callback_attempts', function (Blueprint $t) {
            $t->uuid('contact_center_callback_attempt_uuid')->primary();
            $t->uuid('callback_uuid')->index();
            $t->uuid('execution_uuid')->index();
            $t->string('node_id', 32);
            $t->unsignedBigInteger('generation');
            $t->string('status', 32)->default('preparing');
            $t->uuid('waiter_uuid');
            $t->uuid('anchor_uuid')->nullable();
            $t->uuid('customer_uuid');
            $t->uuid('agent_uuid')->nullable();
            $t->string('token_hash', 64);
            $t->uuid('core_uuid');
            $t->uuid('job_uuid')->nullable();
            $t->timestampTz('originate_finished_at')->nullable();
            $t->timestampTz('confirmed_at')->nullable();
            $t->timestampTz('finished_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('contact_center_webhook_calls', function (Blueprint $t) {
            $t->uuid('contact_center_webhook_call_uuid')->primary();
            $t->string('receiver_node', 32);
            $t->string('name');
            $t->text('url');
            $t->jsonb('headers')->nullable();
            $t->jsonb('payload');
            $t->jsonb('exception')->nullable();
            $t->timestampTz('processed_at')->nullable();
            $t->timestampsTz();
            $t->index(['receiver_node', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['contact_center_webhook_calls', 'contact_center_callback_attempts', 'contact_center_callbacks', 'contact_center_callback_settings', 'contact_center_node_state', 'contact_center_status_events', 'contact_center_changes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
