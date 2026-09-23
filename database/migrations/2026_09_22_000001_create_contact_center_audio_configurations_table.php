<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_center_audio_configurations', function (Blueprint $table) {
            $table->uuid('contact_center_audio_configuration_uuid')->primary();
            $table->uuid('queue_uuid')->unique();
            $table->uuid('domain_uuid')->index();
            $table->string('mode', 16)->default('continuous');
            $table->json('steps')->nullable();
            $table->uuid('requested_revision')->nullable();
            $table->string('active_revision', 64)->nullable();
            $table->string('previous_revision', 64)->nullable();
            $table->string('status', 16)->default('idle');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_center_audio_configurations');
    }
};
