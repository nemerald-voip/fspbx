<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('email_log')) {
            Schema::create('email_log', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->nullable();
                $table->string('from')->nullable();
                $table->string('to')->nullable();
                $table->string('cc')->nullable();
                $table->string('bcc')->nullable();
                $table->string('subject')->nullable();
                $table->longText('text_body')->nullable();
                $table->longText('html_body')->nullable();
                $table->longText('raw_body')->nullable();
                $table->string('status')->nullable();
                $table->json('attachments')->nullable()->default(null);
                $table->longText('sent_debug_info')->nullable();
    
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_log');
    }
};
