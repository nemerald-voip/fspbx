<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('business_hour_exceptions')) {

            Schema::create('business_hour_exceptions', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('business_hour_uuid');
                $table->date('exception_date');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_hour_exceptions');
    }
};
