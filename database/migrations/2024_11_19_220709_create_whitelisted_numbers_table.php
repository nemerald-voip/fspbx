<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWhitelistedNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('whitelisted_numbers')) {
            Schema::create('whitelisted_numbers', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()')); // Primary key
                $table->uuid('domain_uuid');  // Domain UUID (matches the domain in your FreeSWITCH setup)
                $table->string('number', 20); // Whitelisted number
                $table->timestamps();         // Created and updated timestamps
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whitelisted_numbers');
    }
}
