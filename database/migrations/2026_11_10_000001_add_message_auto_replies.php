<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_auto_replies', function (Blueprint $table) {
            $table->uuid('message_auto_reply_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('sms_destination_uuid');
            $table->string('trigger', 16);
            $table->json('keywords')->nullable();
            $table->text('reply');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['domain_uuid', 'sms_destination_uuid']);
            $table->foreign('sms_destination_uuid')->references('sms_destination_uuid')->on('v_sms_destinations')->cascadeOnDelete();
        });
        Schema::table('v_sms_destinations', function (Blueprint $table) {
            $table->boolean('auto_replies_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_auto_replies');
        Schema::table('v_sms_destinations', fn (Blueprint $table) => $table->dropColumn('auto_replies_enabled'));
    }
};
