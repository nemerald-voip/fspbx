<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_receptionist_warm_transfers')) {
            return;
        }

        Schema::table('ai_receptionist_warm_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'consult_openai_call_id')) {
                $table->text('consult_openai_call_id')->nullable()->index();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'consult_sip_call_id')) {
                $table->text('consult_sip_call_id')->nullable()->index();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'consult_freeswitch_uuid')) {
                $table->text('consult_freeswitch_uuid')->nullable()->index();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'decision')) {
                $table->string('decision')->nullable()->index();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'recipient_response')) {
                $table->longText('recipient_response')->nullable();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'failure_reason')) {
                $table->text('failure_reason')->nullable();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }

            if (! Schema::hasColumn('ai_receptionist_warm_transfers', 'declined_at')) {
                $table->timestamp('declined_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_receptionist_warm_transfers')) {
            return;
        }

        Schema::table('ai_receptionist_warm_transfers', function (Blueprint $table) {
            foreach ([
                'declined_at',
                'accepted_at',
                'failure_reason',
                'recipient_response',
                'decision',
                'consult_freeswitch_uuid',
                'consult_sip_call_id',
                'consult_openai_call_id',
            ] as $column) {
                if (Schema::hasColumn('ai_receptionist_warm_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
