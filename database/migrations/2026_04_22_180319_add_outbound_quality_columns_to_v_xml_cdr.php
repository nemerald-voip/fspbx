<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v_xml_cdr', function (Blueprint $table) {
            if (! Schema::hasColumn('v_xml_cdr', 'rtp_audio_out_mos')) {
                $table->decimal('rtp_audio_out_mos', 4, 2)->nullable();
            }
            if (! Schema::hasColumn('v_xml_cdr', 'rtp_audio_in_jitter_ms')) {
                $table->decimal('rtp_audio_in_jitter_ms', 8, 3)->nullable();
            }
            if (! Schema::hasColumn('v_xml_cdr', 'rtp_audio_in_packet_loss')) {
                $table->decimal('rtp_audio_in_packet_loss', 5, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('v_xml_cdr', function (Blueprint $table) {
            if (Schema::hasColumn('v_xml_cdr', 'rtp_audio_out_mos')) {
                $table->dropColumn('rtp_audio_out_mos');
            }
            if (Schema::hasColumn('v_xml_cdr', 'rtp_audio_in_jitter_ms')) {
                $table->dropColumn('rtp_audio_in_jitter_ms');
            }
            if (Schema::hasColumn('v_xml_cdr', 'rtp_audio_in_packet_loss')) {
                $table->dropColumn('rtp_audio_in_packet_loss');
            }
        });
    }
};
