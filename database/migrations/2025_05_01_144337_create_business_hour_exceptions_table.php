<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('business_hour_exceptions')) {
            Schema::create('business_hour_exceptions', function (Blueprint $table) {
                // PK
                $table->uuid('uuid')
                      ->primary()
                      ->default(DB::raw('uuid_generate_v4()'));

                $table->uuid('business_hour_uuid');

                // date-based matching
                $table->date('start_date');           // one-off or range start
                $table->date('end_date')->nullable(); // range end (null = single day)

                // optional time slices
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();

                // floating-holiday matching
                $table->smallInteger('mon')->nullable();   // 1–12
                $table->smallInteger('wday')->nullable();  // 1–7 (Sun=1…Sat=7)
                $table->smallInteger('mweek')->nullable(); // 1–6 (week of month)
                $table->smallInteger('mday')->nullable();  // day of month (1–31)

                // same action/target fields as periods
                $table->text('action');
                $table->string('target_type', 255)->nullable();
                $table->uuid('target_id')->nullable();

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
