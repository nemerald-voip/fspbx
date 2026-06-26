<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_announcement_schedules')) {
            Schema::create('scheduled_announcement_schedules', function (Blueprint $table) {
                $table->uuid('scheduled_announcement_schedule_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('timezone')->nullable();
                $table->string('recording_filename');
                $table->json('extension_uuids');
                $table->string('busy_extension_behavior')->default('skip');
                $table->boolean('enabled')->default(true)->index();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->timestamps();

                $table->index(['domain_uuid', 'enabled']);
            });
        }

        if (! Schema::hasTable('scheduled_announcement_events')) {
            Schema::create('scheduled_announcement_events', function (Blueprint $table) {
                $table->uuid('scheduled_announcement_event_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('scheduled_announcement_schedule_uuid')->index();
                $table->time('time_of_day');
                $table->json('weekdays');
                $table->unsignedSmallInteger('sort_order')->nullable();
                $table->timestamps();

                $table->index(['scheduled_announcement_schedule_uuid', 'sort_order'], 'sched_ann_events_schedule_sort_idx');
            });
        }

        if (! Schema::hasTable('scheduled_announcement_exceptions')) {
            Schema::create('scheduled_announcement_exceptions', function (Blueprint $table) {
                $table->uuid('scheduled_announcement_exception_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('scheduled_announcement_schedule_uuid')->index();
                $table->date('exception_date')->index();
                $table->string('comment')->nullable();
                $table->timestamps();

                $table->unique(['scheduled_announcement_schedule_uuid', 'exception_date'], 'sched_ann_exception_unique');
            });
        }

        if (! Schema::hasTable('scheduled_announcement_runs')) {
            Schema::create('scheduled_announcement_runs', function (Blueprint $table) {
                $table->uuid('scheduled_announcement_run_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('scheduled_announcement_schedule_uuid')->nullable()->index();
                $table->uuid('scheduled_announcement_event_uuid')->nullable()->index();
                $table->string('recording_filename')->nullable();
                $table->string('occurrence_key')->unique();
                $table->timestamp('scheduled_for')->index();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->string('status')->index();
                $table->string('claimed_by_hostname')->nullable();
                $table->string('executed_by_hostname')->nullable();
                $table->json('dns_answers')->nullable();
                $table->text('esl_command')->nullable();
                $table->text('esl_response')->nullable();
                $table->text('error_text')->nullable();
                $table->boolean('manual')->default(false)->index();
                $table->timestamps();

                $table->index(['domain_uuid', 'scheduled_for']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_announcement_runs');
        Schema::dropIfExists('scheduled_announcement_exceptions');
        Schema::dropIfExists('scheduled_announcement_events');
        Schema::dropIfExists('scheduled_announcement_schedules');
    }
};
