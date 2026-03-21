<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('vm_notify_notifications')) {
            Schema::create('vm_notify_notifications', function (Blueprint $table) {
                $table->uuid('vm_notify_notification_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();

                $table->uuid('vm_notify_profile_uuid')->index();

                $table->uuid('voicemail_uuid')->index();
                $table->uuid('voicemail_message_uuid')->index();

                $table->string('status')->nullable()->index();

                $table->uuid('accepted_by_recipient_uuid')->nullable()->index();
                $table->string('accepted_by_number')->nullable();
                $table->timestamp('accepted_at')->nullable();

                $table->unsignedInteger('current_retry')->nullable();
                $table->unsignedInteger('current_priority')->nullable();

                $table->unsignedInteger('max_retry_count')->nullable();
                $table->unsignedInteger('retry_delay_minutes')->nullable();
                $table->unsignedInteger('priority_delay_minutes')->nullable();

                $table->string('caller_id_name')->nullable();
                $table->string('caller_id_number')->nullable();

                $table->string('mailbox')->nullable();
                $table->unsignedInteger('message_length_seconds')->nullable();
                $table->string('message_file_path')->nullable();
                $table->string('message_ext')->nullable();

                $table->timestamp('message_left_at')->nullable();


                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->timestamp('success_email_sent_at')->nullable();
                $table->timestamp('failure_email_sent_at')->nullable();

                $table->timestamps();

                $table->index(['domain_uuid', 'status']);
                $table->index(['vm_notify_profile_uuid', 'status']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('vm_notify_notifications');
    }
};