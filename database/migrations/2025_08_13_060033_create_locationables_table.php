<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locationables')) {
            Schema::create('locationables', function (Blueprint $table) {
                // Primary key (PostgreSQL-friendly default)
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

                // The Location this item belongs to (no FK constraint)
                $table->uuid('location_uuid');

                // Polymorphic target (model + id)
                $table->uuidMorphs('locationable'); // creates locationable_id (uuid) + locationable_type (string)

                $table->timestamps();

                // Helpful indexes
                $table->index(['locationable_type', 'locationable_id'], 'locationables_locationable_idx');

            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locationables');
    }
};
