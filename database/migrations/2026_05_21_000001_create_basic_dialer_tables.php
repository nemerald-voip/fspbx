<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('basic_dialer_contact_lists')) {
            Schema::create('basic_dialer_contact_lists', function (Blueprint $table) {
                $table->uuid('basic_dialer_contact_list_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->timestamps();

                $table->index(['domain_uuid', 'enabled']);
            });
        }

        if (! Schema::hasTable('basic_dialer_contacts')) {
            Schema::create('basic_dialer_contacts', function (Blueprint $table) {
                $table->uuid('basic_dialer_contact_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('basic_dialer_contact_list_uuid')->index();
                $table->string('phone_number')->index();
                $table->string('contact_name')->nullable();
                $table->string('company')->nullable();
                $table->json('custom_fields')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->timestamps();

                $table->index(['basic_dialer_contact_list_uuid', 'enabled'], 'basic_dialer_contacts_list_enabled_idx');
            });
        }

        if (! Schema::hasTable('basic_dialer_campaigns')) {
            Schema::create('basic_dialer_campaigns', function (Blueprint $table) {
                $table->uuid('basic_dialer_campaign_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('basic_dialer_contact_list_uuid')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('draft')->index();
                $table->boolean('enabled')->default(true)->index();
                $table->string('caller_id_name')->nullable();
                $table->string('caller_id_number')->nullable();
                $table->string('destination_type')->nullable();
                $table->string('destination_target')->nullable();
                $table->string('destination_label')->nullable();
                $table->unsignedInteger('max_concurrent_calls')->default(1);
                $table->unsignedInteger('seconds_between_calls')->default(5);
                $table->unsignedInteger('retry_limit')->default(0);
                $table->unsignedInteger('retry_delay_minutes')->default(60);
                $table->unsignedInteger('originate_timeout')->default(30);
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['domain_uuid', 'status']);
            });
        }

        if (! Schema::hasTable('basic_dialer_campaign_recipients')) {
            Schema::create('basic_dialer_campaign_recipients', function (Blueprint $table) {
                $table->uuid('basic_dialer_campaign_recipient_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('basic_dialer_campaign_uuid')->index();
                $table->uuid('basic_dialer_contact_uuid')->nullable()->index();
                $table->string('phone_number')->index();
                $table->string('contact_name')->nullable();
                $table->string('status')->default('pending')->index();
                $table->unsignedInteger('attempts_count')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->string('last_outcome')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['basic_dialer_campaign_uuid', 'status'], 'basic_dialer_recipients_campaign_status_idx');
            });
        }

        if (! Schema::hasTable('basic_dialer_campaign_attempts')) {
            Schema::create('basic_dialer_campaign_attempts', function (Blueprint $table) {
                $table->uuid('basic_dialer_campaign_attempt_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('basic_dialer_campaign_uuid')->index();
                $table->uuid('basic_dialer_campaign_recipient_uuid')->index();
                $table->uuid('call_uuid')->nullable()->index();
                $table->uuid('xml_cdr_uuid')->nullable()->index();
                $table->unsignedInteger('attempt_number')->default(1);
                $table->string('status')->default('queued')->index();
                $table->string('outcome')->nullable()->index();
                $table->text('command')->nullable();
                $table->text('response')->nullable();
                $table->string('hangup_cause')->nullable();
                $table->integer('duration')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['basic_dialer_campaign_uuid', 'status'], 'basic_dialer_attempts_campaign_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('basic_dialer_campaign_attempts');
        Schema::dropIfExists('basic_dialer_campaign_recipients');
        Schema::dropIfExists('basic_dialer_campaigns');
        Schema::dropIfExists('basic_dialer_contacts');
        Schema::dropIfExists('basic_dialer_contact_lists');
    }
};
