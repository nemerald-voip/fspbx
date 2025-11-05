<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('call_transcriptions')) {
            return; // table already exists, skip
        }

        Schema::create('call_transcriptions', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('xml_cdr_uuid')->nullable();
            $table->uuid('domain_uuid')->nullable();
            $table->string('provider_key')->nullable();
            $table->string('external_id')->nullable(); 

            // replaced enum with string
            $table->string('status', 32)->default('pending'); // pending|queued|processing|completed|failed
            $table->text('error_message')->nullable();

            // Inputs and outputs (raw JSON from/to provider)
            $table->jsonb('request_payload')->nullable();   // what we sent
            $table->jsonb('response_payload')->nullable();  // last provider response
            $table->jsonb('result_payload')->nullable();    // final result (transcript, chapters, etc.)

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('summary_status', 32)->nullable()->index(); // pending|processing|completed|failed
            $table->text('summary_error')->nullable();
            $table->jsonb('summary_payload')->nullable(); // your final structured JSON (summary, participants, ...)

            $table->timestamp('summary_requested_at')->nullable();
            $table->timestamp('summary_completed_at')->nullable();

            $table->timestamps();

            $table->index(['xml_cdr_uuid']);
            $table->index(['domain_uuid']);
            $table->index(['provider_key', 'external_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcriptions');
    }
};

