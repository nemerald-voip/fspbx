<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateways')) {
            // Nothing to do if the table doesn't exist
            return;
        }
        
        DB::statement("ALTER TABLE public.payment_gateways ALTER COLUMN uuid SET NOT NULL;");
        DB::statement("ALTER TABLE public.payment_gateways ADD CONSTRAINT payment_gateways_pkey PRIMARY KEY (uuid);");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE public.payment_gateways DROP CONSTRAINT IF EXISTS payment_gateways_pkey;");
        // (Optional) revert NOT NULL:
        // DB::statement("ALTER TABLE public.payment_gateways ALTER COLUMN uuid DROP NOT NULL;");
    }
};