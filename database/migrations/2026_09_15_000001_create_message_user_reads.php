<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_user_reads', function (Blueprint $table) {
            $table->uuid('message_user_read_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('user_uuid');
            $table->uuid('message_uuid');
            $table->timestampTz('read_at');
            $table->unique(['user_uuid', 'message_uuid']);
            $table->foreign('message_uuid')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $table->foreign('user_uuid')->references('user_uuid')->on('v_users')->cascadeOnDelete();
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['domain_uuid', 'source', 'destination', 'direction', 'created_at'], 'messages_conversation_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_user_reads');
        Schema::table('messages', fn (Blueprint $table) => $table->dropIndex('messages_conversation_unread_index'));
    }
};
