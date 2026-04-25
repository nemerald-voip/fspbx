<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v_ai_agent_kb_documents')) {
            Schema::create('v_ai_agent_kb_documents', function (Blueprint $table) {
                $table->uuid('kb_document_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('ai_agent_uuid')->index();
                $table->uuid('domain_uuid')->index();
                $table->string('document_type', 10); // file | url | text
                $table->string('elevenlabs_documentation_id', 255)->nullable();
                $table->string('name', 255);
                $table->string('file_path', 1024)->nullable();
                $table->string('file_mime_type', 255)->nullable();
                $table->integer('file_size')->nullable();
                $table->string('url', 2048)->nullable();
                $table->text('text_content')->nullable();
                $table->string('sync_status', 20)->default('pending'); // pending | synced | failed
                $table->text('sync_error')->nullable();
                $table->timestamp('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestamp('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('v_ai_agent_kb_documents');
    }
};
