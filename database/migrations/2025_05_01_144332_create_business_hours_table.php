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
        if (!Schema::hasTable('business_hours')) {
            Schema::create('business_hours', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid');
                $table->uuid('dialplan_uuid')->nullable();
                $table->string('name');
                $table->string('extension');
                $table->string('timezone')->nullable();
                $table->string('context');
                $table->text('description')->nullable();
                $table->boolean('enabled');
                $table->timestamps();
    
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
