<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVSmsMessagesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('v_sms_messages')) {
            Schema::create('v_sms_messages', function (Blueprint $table) {
                $table->uuid('sms_message_uuid')->primary();
                $table->uuid('extension_uuid')->nullable();
                $table->uuid('domain_uuid')->nullable();
                $table->timestamp('start_stamp')->nullable();
                $table->text('from_number')->nullable();
                $table->text('to_number')->nullable();
                $table->text('message')->nullable();
                $table->text('direction')->nullable();
                $table->text('response')->nullable();
                $table->text('carrier')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('v_sms_messages');
    }
}
