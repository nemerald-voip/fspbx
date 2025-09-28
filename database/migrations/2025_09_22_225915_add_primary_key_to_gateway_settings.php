<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('gateway_settings')) {
            return;
        }

        // 1) Ensure the uuid column exists (add nullable with temp default for backfill)
        if (!Schema::hasColumn('gateway_settings', 'uuid')) {
            Schema::table('gateway_settings', function (Blueprint $table) {
                // Swap uuid_generate_v4() -> gen_random_uuid() if you use pgcrypto
                $table->uuid('uuid')->nullable()->default(DB::raw('uuid_generate_v4()'));
            });
        }

        // 2) Backfill any NULL uuids
        DB::statement("UPDATE public.gateway_settings SET uuid = uuid_generate_v4() WHERE uuid IS NULL;");

        // 3) Enforce NOT NULL
        DB::statement("ALTER TABLE public.gateway_settings ALTER COLUMN uuid SET NOT NULL;");

        // 4) Add PK only if it doesn't already exist
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'gateway_settings_pkey'
          AND conrelid = 'public.gateway_settings'::regclass
    ) THEN
        ALTER TABLE public.gateway_settings
        ADD CONSTRAINT gateway_settings_pkey PRIMARY KEY (uuid);
    END IF;
END
$$;
SQL);

        // 5) (Optional) Drop the default now that rows are populated
        DB::statement("ALTER TABLE public.gateway_settings ALTER COLUMN uuid DROP DEFAULT;");
    }

    public function down(): void
    {
        // Drop PK if present
        DB::statement("ALTER TABLE public.gateway_settings DROP CONSTRAINT IF EXISTS gateway_settings_pkey;");

        // (Optional) Allow NULLs again
        // DB::statement("ALTER TABLE public.gateway_settings ALTER COLUMN uuid DROP NOT NULL;");
    }
};

