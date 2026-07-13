<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_webhook_subscriptions')) {
            return;
        }

        Schema::create('call_webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('call_webhook_uuid')->primary();
            $table->uuid('domain_uuid')->unique();
            $table->text('endpoint_url');
            $table->text('signing_secret');
            $table->boolean('enabled')->default(true);
            $table->json('events');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_webhook_subscriptions');
    }
};
