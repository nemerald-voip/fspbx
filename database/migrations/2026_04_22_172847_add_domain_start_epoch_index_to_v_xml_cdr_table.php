<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS v_xml_cdr_domain_uuid_start_epoch_idx ON v_xml_cdr (domain_uuid, start_epoch DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS v_xml_cdr_domain_uuid_start_epoch_idx');
    }
};
