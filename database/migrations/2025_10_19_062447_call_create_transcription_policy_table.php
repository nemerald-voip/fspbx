<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('call_transcription_policy')) {
            Schema::create('call_transcription_policy', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->nullable();
                $table->boolean('enabled')->default(false);
                $table->boolean('auto_transcribe')->default(false);
                $table->uuid('provider_uuid')->nullable(); 
                $table->timestamps();

                $table->index('domain_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcription_policy');
    }
};
