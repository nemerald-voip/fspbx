<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_opt_outs')) {
            Schema::create('message_opt_outs', function (Blueprint $table) {
                $table->uuid('message_opt_out_uuid')->primary();
                $table->uuid('domain_uuid');
                // FS PBX number where the opt-out was recorded; scope is a lookup setting.
                $table->string('local_number', 16)->default('');
                $table->string('phone_number', 16);
                $table->string('source', 16);
                $table->timestamps();
                $table->unique(['domain_uuid', 'local_number', 'phone_number'], 'message_opt_out_number_unique');
                $table->index(['domain_uuid', 'phone_number']);
            });
        }

        if (! Schema::hasTable('message_auto_replies')) {
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
        }

        if (! Schema::hasColumn('v_sms_destinations', 'opt_out_enabled')) {
            Schema::table('v_sms_destinations', function (Blueprint $table) {
                $table->boolean('opt_out_enabled')->default(false);
            });
        }

        if (! Schema::hasColumn('v_sms_destinations', 'auto_replies_enabled')) {
            Schema::table('v_sms_destinations', function (Blueprint $table) {
                $table->boolean('auto_replies_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_auto_replies');
        Schema::dropIfExists('message_opt_outs');

        if (Schema::hasColumn('v_sms_destinations', 'auto_replies_enabled')) {
            Schema::table('v_sms_destinations', fn (Blueprint $table) => $table->dropColumn('auto_replies_enabled'));
        }

        if (Schema::hasColumn('v_sms_destinations', 'opt_out_enabled')) {
            Schema::table('v_sms_destinations', fn (Blueprint $table) => $table->dropColumn('opt_out_enabled'));
        }
    }
};
