<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVSmsDestinationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('v_sms_destinations')) {
            Schema::create('v_sms_destinations', function (Blueprint $table) {
                $table->uuid('sms_destination_uuid')->primary();
                $table->uuid('domain_uuid')->nullable();
                $table->text('destination')->nullable();
                $table->text('carrier')->nullable();
                $table->text('enabled')->nullable();
                $table->text('description')->nullable();
                $table->text('chatplan_detail_data')->nullable();
                $table->text('email')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('v_sms_destinations');
    }
}

