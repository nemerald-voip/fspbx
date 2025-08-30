<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hotel_rooms')) {
            return; // already there — nothing to do
        }
    
        Schema::create('hotel_rooms', function (Blueprint $t) {
            $t->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $t->uuid('domain_uuid')->index();
            $t->uuid('extension_uuid')->nullable()->index(); // maps to v_extensions
            $t->string('room_name', 32);
    
            $t->timestamps();
        });
    }
    
    public function down(): void { Schema::dropIfExists('hotel_rooms'); }
};

