<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_destination_members', function (Blueprint $table) {
            $table->uuid('domain_uuid');
            $table->uuid('sms_destination_uuid');
            $table->uuid('extension_uuid');
            $table->primary(['sms_destination_uuid', 'extension_uuid']);
            $table->index(['domain_uuid', 'extension_uuid']);
        });
        Schema::table('ringotel_message_syncs', function (Blueprint $table) {
            $table->timestamp('targets_initialized_at')->nullable();
        });
        Schema::create('ringotel_message_deliveries', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->uuid('message_uuid');
            $table->foreign('message_uuid')->references('message_uuid')->on('messages')->cascadeOnDelete();
            $table->string('conversation_id', 64);
            $table->string('status')->default('pending');
            $table->json('parts')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['message_uuid', 'conversation_id']);
        });
        // Preserve every previous result, including uncertain requests. Never replay
        // historical messages merely because a second participant is added later.
        DB::table('ringotel_message_syncs')->whereNotNull('conversation_id')->orderBy('message_uuid')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ringotel_message_deliveries')->insert([
                        'id' => hash('sha256', $row->message_uuid.'|'.$row->conversation_id),
                        'message_uuid' => $row->message_uuid, 'conversation_id' => $row->conversation_id,
                        'status' => $row->status, 'parts' => $row->parts, 'error' => $row->error,
                        'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                    ]);
                    DB::table('ringotel_message_syncs')->where('message_uuid', $row->message_uuid)
                        ->update(['targets_initialized_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringotel_message_deliveries');
        Schema::table('ringotel_message_syncs', fn (Blueprint $table) => $table->dropColumn('targets_initialized_at'));
        Schema::dropIfExists('sms_destination_members');
    }
};
