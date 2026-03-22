<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('vm_notify_attempts')) {
            Schema::create('vm_notify_attempts', function (Blueprint $table) {
                $table->uuid('vm_notify_attempt_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();

                $table->uuid('vm_notify_notification_uuid')->index();
                $table->uuid('vm_notify_profile_recipient_uuid')->nullable()->index();

                $table->unsignedInteger('retry_number')->nullable()->index();
                $table->unsignedInteger('priority')->nullable()->index();

                $table->string('destination')->nullable()->index();
                $table->uuid('call_uuid')->nullable()->index();

                $table->string('status')->nullable()->index();

                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();

                $table->string('dtmf_sequence')->nullable();

                $table->timestamp('claim_attempted_at')->nullable();
                $table->string('claim_result')->nullable()->index();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['vm_notify_notification_uuid', 'status']);
                $table->index(['vm_notify_notification_uuid', 'retry_number', 'priority'], 'vm_notify_attempts_notification_retry_priority_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('vm_notify_attempts');
    }
};