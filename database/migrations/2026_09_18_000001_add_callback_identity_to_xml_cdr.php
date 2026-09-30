<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Retain call history independently of optional module retention.
        if (! Schema::hasColumn('v_xml_cdr', 'cc_callback_attempt_uuid')) {
            Schema::table('v_xml_cdr', fn (Blueprint $table) => $table->uuid('cc_callback_attempt_uuid')->nullable());
        }
        if (! Schema::hasColumn('v_xml_cdr', 'cc_callback_role')) {
            Schema::table('v_xml_cdr', fn (Blueprint $table) => $table->string('cc_callback_role', 16)->nullable());
        }
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS v_xml_cdr_callback_attempt_idx '
            .'ON v_xml_cdr (cc_callback_attempt_uuid) WHERE cc_callback_attempt_uuid IS NOT NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS v_xml_cdr_cc_member_session_idx '
            .'ON v_xml_cdr (cc_member_session_uuid) WHERE cc_member_session_uuid IS NOT NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS v_xml_cdr_originating_leg_idx '
            .'ON v_xml_cdr (originating_leg_uuid) WHERE originating_leg_uuid IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS v_xml_cdr_cc_member_session_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS v_xml_cdr_originating_leg_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS v_xml_cdr_callback_attempt_idx');
        Schema::table('v_xml_cdr', function (Blueprint $table) {
            $table->dropColumn(['cc_callback_attempt_uuid', 'cc_callback_role']);
        });
    }
};
