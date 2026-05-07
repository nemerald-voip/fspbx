<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('outbound_faxes')) {
            Schema::create('outbound_faxes', function (Blueprint $t) {
                $t->uuid('outbound_fax_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

                $t->uuid('domain_uuid')->index();
                $t->uuid('fax_uuid')->index();

                // Lifecycle: waiting → sending → sent / failed / busy / trying
                $t->string('status', 16)->default('waiting')->index();

                // Phone numbers
                $t->string('source', 64)->nullable();           // outbound caller-ID number
                $t->string('source_name', 128)->nullable();     // outbound caller-ID display
                $t->string('destination', 64);                  // number we're faxing to
                $t->string('destination_name', 128)->nullable();

                // Sender + notification recipient (same address)
                $t->string('email', 255)->nullable();

                // Cover-page content
                $t->string('subject', 255)->nullable();
                $t->text('body')->nullable();

                // File on the fax disk (final TIF). PDF lives next to it with .pdf extension.
                $t->text('file_path');
                $t->unsignedSmallInteger('total_pages')->nullable();

                // Outbound dialing context
                $t->string('prefix', 16)->nullable();
                $t->string('accountcode', 64)->nullable();

                // Retry tracking
                $t->unsignedSmallInteger('retry_count')->default(0);
                $t->unsignedSmallInteger('retry_limit')->default(5);
                $t->timestampTz('retry_at')->nullable()->index();

                // Most recent originate command + ESL reply (debugging)
                $t->text('command')->nullable();
                $t->text('response')->nullable();

                // FreeSWITCH call leg UUID for the in-flight attempt
                $t->uuid('call_uuid')->nullable()->index();

                // Bumped each SendFaxJob run; the webhook handler matches against this
                // to ignore stale hangup webhooks from orphaned earlier attempts.
                $t->uuid('current_attempt_uuid')->nullable()->index();

                // Email-result notification tracking
                $t->timestampTz('notify_sent_at')->nullable();

                $t->timestampsTz();
            });
        }

        Schema::table('v_fax_logs', function (Blueprint $t) {
            if (!Schema::hasColumn('v_fax_logs', 'outbound_fax_uuid')) {
                $t->uuid('outbound_fax_uuid')->nullable()->index();
            }
            if (!Schema::hasColumn('v_fax_logs', 'outbound_fax_attempt_uuid')) {
                $t->uuid('outbound_fax_attempt_uuid')->nullable()->index();
            }
            if (!Schema::hasColumn('v_fax_logs', 'source')) {
                $t->string('source', 64)->nullable();
            }
            if (!Schema::hasColumn('v_fax_logs', 'destination')) {
                $t->string('destination', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('v_fax_logs', function (Blueprint $t) {
            foreach (['outbound_fax_uuid', 'outbound_fax_attempt_uuid', 'source', 'destination'] as $col) {
                if (Schema::hasColumn('v_fax_logs', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('outbound_faxes')) {
            Schema::dropIfExists('outbound_faxes');
        }
    }
};
