<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFaxAllowedEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fax_allowed_emails', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('fax_uuid');
            $table->foreign('fax_uuid') ->references('fax_uuid')->on('v_fax')->onDelete('cascade');
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fax_allowed_emails');
    }
}
