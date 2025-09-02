<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hotel_housekeeping_definitions')) {
            Schema::create('hotel_housekeeping_definitions', function (Blueprint $t) {
                $t->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $t->uuid('domain_uuid')->nullable()->index();  // NULL = global
                $t->smallInteger('code')->nullable();                      // 0..99
                $t->string('label', 64)->nullable();
                $t->boolean('enabled')->default(true);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_housekeeping_definitions');
    }
};
