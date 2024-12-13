<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDescriptionToWhitelistedNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('whitelisted_numbers', 'description')) {
            Schema::table('whitelisted_numbers', function (Blueprint $table) {
                $table->text('description')->nullable()->after('number'); // Adds the description field
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
        if (Schema::hasColumn('whitelisted_numbers', 'description')) {
            Schema::table('whitelisted_numbers', function (Blueprint $table) {
                $table->dropColumn('description'); // Removes the description field
            });
        }
    }
}

