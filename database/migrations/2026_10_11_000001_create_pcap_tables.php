<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pcap_sessions')) {
            Schema::create('pcap_sessions', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->string('name');
                // 1h, 4h, 8h, 24h, 48h or 1w
                $table->string('duration', 20);
                // Any of inbound, outbound, local
                $table->json('directions');
                // all or extensions
                $table->string('scope', 20)->default('all');
                $table->json('extension_uuids')->nullable();
                $table->timestamp('started_at');
                // Capturing stops at this time.
                $table->timestamp('expires_at')->index();
                $table->timestamp('stopped_at')->nullable();
                // Files are kept this long after capturing ends (24, 48 or 72).
                $table->integer('keep_hours')->default(24);
                // The session and all of its files are deleted at this time.
                $table->timestamp('delete_at')->index();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pcap_call_legs')) {
            // Short-lived: one row per hung-up channel while an account is capturing.
            Schema::create('pcap_call_legs', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                // The A-leg UUID shared by every leg of the call.
                $table->uuid('call_uuid')->index();
                $table->uuid('channel_uuid')->unique();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->string('node')->index();
                // Related channel UUIDs; legs sharing one are the same call.
                $table->json('linked_uuids')->nullable();
                // FreeSWITCH's own view: inbound for a leg it received, outbound for one it placed.
                $table->string('channel_direction', 20)->nullable();
                $table->string('direction', 20)->nullable();
                $table->string('caller_id_name')->nullable();
                $table->string('caller_id_number')->nullable();
                $table->string('destination_number')->nullable();
                // Extension numbers this leg belongs to.
                $table->json('numbers')->nullable();
                $table->string('sip_call_id')->nullable();
                $table->integer('local_media_port')->nullable();
                $table->string('remote_media_ip')->nullable();
                $table->integer('remote_media_port')->nullable();
                $table->bigInteger('created_epoch');
                $table->bigInteger('hangup_epoch');
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pcap_captures')) {
            Schema::create('pcap_captures', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('pcap_session_uuid')->index();
                $table->uuid('domain_uuid')->index();
                $table->uuid('call_uuid');
                $table->string('node');
                $table->string('direction', 20)->nullable();
                $table->string('caller_id_name')->nullable();
                $table->string('caller_id_number')->nullable();
                $table->string('destination_number')->nullable();
                $table->timestamp('call_started_at')->nullable();
                $table->integer('duration')->default(0);
                $table->integer('legs')->default(1);
                // ready, empty or failed
                $table->string('status', 20);
                $table->string('file_path')->nullable();
                $table->bigInteger('file_size')->default(0);
                $table->integer('packet_count')->default(0);
                $table->text('error')->nullable();
                $table->timestamps();

                $table->unique(['pcap_session_uuid', 'call_uuid']);
                $table->foreign('pcap_session_uuid')->references('uuid')->on('pcap_sessions')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcap_captures');
        Schema::dropIfExists('pcap_call_legs');
        Schema::dropIfExists('pcap_sessions');
    }
};
