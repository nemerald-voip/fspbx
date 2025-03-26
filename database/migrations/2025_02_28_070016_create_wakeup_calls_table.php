<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWakeupCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('wakeup_calls')) {
            Schema::create('wakeup_calls', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()')); 
                $table->uuid('domain_uuid'); 
                $table->uuid('extension_uuid');
                $table->timestamp('wake_up_time'); // Fixed time for recurring calls
                $table->timestamp('next_attempt_at')->nullable(); // Next scheduled attempt
                $table->boolean('recurring')->default(false); // True for daily recurring calls
                $table->string('status', 50)->nullable();
                $table->integer('retry_count')->default(0); 
                $table->timestamps(); 
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
        Schema::dropIfExists('wakeup_calls');
    }
}
