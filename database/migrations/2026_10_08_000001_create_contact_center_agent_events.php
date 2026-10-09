<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_center_agent_events', function (Blueprint $table) {
            $table->uuid('contact_center_agent_event_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('agent_uuid');
            // Availability belongs to the agent, not to an individual queue.
            $table->uuid('queue_uuid')->nullable();
            // Logical HA changes may be confirmed through the status API;
            // native observations still retain their FreeSWITCH core UUID.
            $table->uuid('core_uuid')->nullable();
            $table->string('event_identity', 64)->unique();
            $table->bigInteger('event_us');
            $table->string('event_type', 24)->default('offer_failed');
            $table->uuid('observer_session_uuid')->nullable();
            $table->bigInteger('event_sequence')->nullable();
            $table->string('previous_status', 32)->nullable();
            // The previous availability interval ends at event_us. Keeping its
            // start on the closing event makes duration reporting self-contained.
            $table->bigInteger('previous_status_started_us')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('reason', 40)->nullable();
            $table->string('cause', 80)->nullable();
            $table->bigInteger('cooldown_start')->nullable();
            $table->bigInteger('cooldown_end')->nullable();
            // A later sample can establish a change, but not its exact time.
            $table->bigInteger('changed_observed_epoch')->nullable();
            $table->timestampTz('created_at');
            $table->index(['domain_uuid', 'agent_uuid', 'event_us'], 'cc_agent_event_agent_time');
            $table->index(['domain_uuid', 'queue_uuid', 'event_us'], 'cc_agent_event_queue_time');
            $table->index(['domain_uuid', 'cooldown_end'], 'cc_agent_event_interval');
            $table->index('event_us', 'cc_agent_event_retention');
            $table->index(['agent_uuid', 'observer_session_uuid', 'event_us'], 'cc_agent_event_status_anchor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_center_agent_events');
    }
};
