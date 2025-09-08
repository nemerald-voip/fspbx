<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hotel_room_status')) {
            return; // already exists
        }
    
        Schema::create('hotel_room_status', function (Blueprint $t) {
            $t->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $t->uuid('domain_uuid')->index();
            $t->uuid('hotel_room_uuid')->index();
            $t->string('occupancy_status')->nullable();
            $t->uuid('housekeeping_status')->nullable();
            $t->string('guest_first_name')->nullable();
            $t->string('guest_last_name')->nullable();
            $t->date('arrival_date')->nullable();
            $t->date('departure_date')->nullable();
            $t->timestamps();
        });
    }
    
    public function down(): void { Schema::dropIfExists('hotel_room_status'); }
};

