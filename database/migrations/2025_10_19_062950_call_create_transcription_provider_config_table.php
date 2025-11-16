<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('call_transcription_provider_config')) {
            Schema::create('call_transcription_provider_config', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->nullable();     // NULL = system scope
                $table->uuid('provider_uuid');                 // FK -> provider
                $table->jsonb('config')->nullable();         // credentials + options (JSONB)
                $table->timestamps();

                $table->index(['provider_uuid', 'domain_uuid']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcription_provider_config');
    }
};
