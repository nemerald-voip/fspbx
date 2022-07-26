<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeUUID extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('archive_recording', function (Blueprint $table) {
             $table->dropColumn('call_recording_uuid');
             $table->uuid('xml_cdr_uuid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('archive_recording', function (Blueprint $table) {
             $table->dropColumn('xml_cdr_uuid');
             $table->uuid('call_recording_uuid')->nullable();
        });
    }
}
