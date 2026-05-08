<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUuidPrimaryKey('payment_gateways', 'payment_gateways_pkey');
        $this->ensureUuidPrimaryKey('gateway_settings', 'gateway_settings_pkey');

        if (Schema::hasTable('payment_gateways')) {
            DB::statement('ALTER TABLE public.payment_gateways DROP CONSTRAINT IF EXISTS payment_gateways_slug_unique;');
        }
    }

    public function down(): void
    {
        // Intentionally no-op. This is a repair migration and should not remove
        // primary keys that may have existed before this migration ran.
    }

    private function ensureUuidPrimaryKey(string $tableName, string $constraintName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        if (!Schema::hasColumn($tableName, 'uuid')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->default(DB::raw('uuid_generate_v4()'));
            });
        }

        DB::statement("UPDATE public.{$tableName} SET uuid = uuid_generate_v4() WHERE uuid IS NULL;");
        DB::statement("ALTER TABLE public.{$tableName} ALTER COLUMN uuid SET DEFAULT uuid_generate_v4();");
        DB::statement("ALTER TABLE public.{$tableName} ALTER COLUMN uuid SET NOT NULL;");

        DB::statement(<<<SQL
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE contype = 'p'
          AND conrelid = 'public.{$tableName}'::regclass
    ) THEN
        ALTER TABLE public.{$tableName}
        ADD CONSTRAINT {$constraintName} PRIMARY KEY (uuid);
    END IF;
END
$$;
SQL);
    }
};
