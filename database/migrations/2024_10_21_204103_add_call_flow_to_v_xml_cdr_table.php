<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('v_xml_cdr', function (Blueprint $table) {
            if (!Schema::hasColumn('v_xml_cdr', 'call_flow')) {
                $table->jsonb('call_flow')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('v_xml_cdr', function (Blueprint $table) {
            if (Schema::hasColumn('v_xml_cdr', 'call_flow')) {
                $table->dropColumn('call_flow');
            }
        });
    }
};
