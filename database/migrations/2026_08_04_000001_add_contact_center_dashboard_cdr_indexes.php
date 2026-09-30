<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('v_xml_cdr')) {
            return;
        }

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS v_xml_cdr_cc_queue_start_idx '
            .'ON v_xml_cdr (call_center_queue_uuid, start_stamp) '
            .'WHERE call_center_queue_uuid IS NOT NULL'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS v_xml_cdr_extension_start_outbound_idx '
            .'ON v_xml_cdr (extension_uuid, start_stamp) '
            ."WHERE extension_uuid IS NOT NULL AND direction = 'outbound'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS v_xml_cdr_cc_queue_start_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS v_xml_cdr_extension_start_outbound_idx');
    }
};
