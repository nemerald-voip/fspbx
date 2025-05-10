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
        if (!Schema::hasTable('business_hour_periods')) {

            Schema::create('business_hour_periods', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('business_hour_uuid');
                $table->smallInteger('day_of_week');    // 1=Mon … 7=Sun
                $table->time('start_time');
                $table->time('end_time');
                $table->text('action')->nullable();
                // polymorphic target: model type + model id (UUID)
                $table->nullableUuidMorphs('target');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_hour_periods');
    }
};
