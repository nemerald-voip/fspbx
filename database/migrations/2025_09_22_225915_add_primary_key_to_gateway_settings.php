<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('gateway_settings')) {
            // Nothing to do if the table doesn't exist
            return;
        }

        DB::statement("ALTER TABLE public.gateway_settings ALTER COLUMN uuid SET NOT NULL;");
        DB::statement("ALTER TABLE public.gateway_settings ADD CONSTRAINT gateway_settings_pkey PRIMARY KEY (uuid);");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE public.gateway_settings DROP CONSTRAINT IF EXISTS gateway_settings_pkey;");
        // (Optional) revert NOT NULL:
        // DB::statement("ALTER TABLE public.gateway_settings ALTER COLUMN uuid DROP NOT NULL;");
    }
};
