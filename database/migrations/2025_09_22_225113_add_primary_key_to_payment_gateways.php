<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateways')) {
            return;
        }

        // 1) Ensure the uuid column exists (with a temporary default to backfill)
        if (!Schema::hasColumn('payment_gateways', 'uuid')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                // If you use pgcrypto instead, swap to gen_random_uuid()
                $table->uuid('uuid')->nullable()->default(DB::raw('uuid_generate_v4()'));
            });
        }

        // 3) Enforce NOT NULL
        DB::statement("ALTER TABLE public.payment_gateways ALTER COLUMN uuid SET NOT NULL;");

        // 4) Add PK only if it doesn't already exist
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'payment_gateways_pkey'
          AND conrelid = 'public.payment_gateways'::regclass
    ) THEN
        ALTER TABLE public.payment_gateways
        ADD CONSTRAINT payment_gateways_pkey PRIMARY KEY (uuid);
    END IF;
END
$$;
SQL);

    }

    public function down(): void
    {
        // Drop PK if present
        DB::statement("ALTER TABLE public.payment_gateways DROP CONSTRAINT IF EXISTS payment_gateways_pkey;");

        // (Optional) Allow NULLs again
        // DB::statement("ALTER TABLE public.payment_gateways ALTER COLUMN uuid DROP NOT NULL;");
    }
};
