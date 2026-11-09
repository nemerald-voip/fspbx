<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_webhook_settings')) {
            Schema::create('message_webhook_settings', function (Blueprint $table) {
                $table->uuid('message_webhook_setting_uuid')->primary();
                $table->uuid('domain_uuid')->unique();
                $table->text('endpoint_url')->nullable();
                $table->text('signing_secret');
                $table->timestamps();
            });
        }
        foreach (['inbound_delivery', 'inbound_webhook_url'] as $column) {
            if (! Schema::hasColumn('v_sms_destinations', $column)) {
                Schema::table('v_sms_destinations', function (Blueprint $table) use ($column) {
                    $table->text($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['inbound_delivery', 'inbound_webhook_url'] as $column) {
            if (Schema::hasColumn('v_sms_destinations', $column)) {
                Schema::table('v_sms_destinations', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        Schema::dropIfExists('message_webhook_settings');
    }
};
