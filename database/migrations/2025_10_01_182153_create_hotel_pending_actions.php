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
        if (!Schema::hasTable('hotel_pending_actions')) {
            Schema::create('hotel_pending_actions', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('hotel_room_uuid')->index();
                $table->char('smdr_type', 1)->index();      // 'S','C','P','W','D'  (we start with 'S')
                $table->jsonb('data');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_pending_actions');
    }
};
