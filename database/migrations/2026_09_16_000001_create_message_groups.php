<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('local_number', 20);
            $table->json('recipients');
            $table->timestamps();
            $table->index(['domain_uuid', 'local_number']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->nullable();
            $table->foreign('message_group_uuid')->references('message_group_uuid')->on('message_groups');
            $table->index(['domain_uuid', 'message_group_uuid', 'created_at'], 'messages_group_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['message_group_uuid']);
            $table->dropIndex('messages_group_history_index');
            $table->dropColumn('message_group_uuid');
        });
        Schema::dropIfExists('message_groups');
    }
};
