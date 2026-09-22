<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ringotel_conversations', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->uuid('domain_uuid');
            $table->uuid('extension_uuid');
            $table->string('org_id');
            $table->string('user_id');
            $table->string('local_number');
            $table->string('remote_number');
            $table->string('remote_identifier');
            $table->string('session_id')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'session_id']);
        });

        Schema::create('ringotel_message_syncs', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            $table->foreign('message_uuid')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $table->string('conversation_id', 64)->nullable();
            $table->string('status')->default('pending');
            $table->json('parts')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringotel_message_syncs');
        Schema::dropIfExists('ringotel_conversations');
    }
};
