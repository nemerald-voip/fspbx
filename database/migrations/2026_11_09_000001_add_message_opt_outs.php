<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::table('v_sms_destinations', function (Blueprint $table) {
            $table->boolean('opt_out_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('v_sms_destinations', fn (Blueprint $table) => $table->dropColumn('opt_out_enabled'));
        Schema::dropIfExists('message_opt_outs');
    }
};
