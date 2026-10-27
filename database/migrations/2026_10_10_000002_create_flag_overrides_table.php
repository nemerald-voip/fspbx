<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flag_overrides')) {
            Schema::create('flag_overrides', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                // ring_group or ivr_menu
                $table->string('owner_type', 20);
                $table->uuid('owner_uuid');
                $table->uuid('flag_uuid')->index();
                // Destination used while the flag is active.
                $table->string('override_app');
                $table->text('override_data')->nullable();
                // Lower runs first; the first active flag wins.
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['owner_type', 'owner_uuid']);
                $table->unique(['owner_type', 'owner_uuid', 'flag_uuid']);
                $table->foreign('flag_uuid')->references('uuid')->on('flags')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_overrides');
    }
};
