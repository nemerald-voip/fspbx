<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWakeupAuthExtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('wakeup_auth_ext')) {
            Schema::create('wakeup_auth_ext', function (Blueprint $table) {
                // Unique UUID primary key for each record
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()')); 

                // Domain UUID and extension UUID fields
                $table->uuid('domain_uuid');
                $table->uuid('extension_uuid');

                // Timestamps for record tracking
                $table->timestamps();

                // Unique constraint for each domain and extension combination
                $table->unique(['domain_uuid', 'extension_uuid']);
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
        Schema::dropIfExists('wakeup_auth_ext');
    }
}
