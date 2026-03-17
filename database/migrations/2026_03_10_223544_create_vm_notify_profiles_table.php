<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('vm_notify_profiles')) {
            Schema::create('vm_notify_profiles', function (Blueprint $table) {
                $table->uuid('vm_notify_profile_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('voicemail_uuid')->index();

                $table->string('name');
                $table->text('description')->nullable();

                $table->boolean('enabled')->default(true)->index();

                $table->string('outbound_cid_mode')->default('default');
                $table->string('fixed_caller_id_number')->nullable();
                $table->string('fixed_caller_id_name')->nullable();
                $table->string('internal_caller_id_name')->nullable();

                $table->unsignedInteger('retry_count')->nullable();
                $table->unsignedInteger('retry_delay_minutes')->nullable();
                $table->unsignedInteger('priority_delay_minutes')->nullable();

                $table->string('email_from')->nullable();
                $table->json('email_success')->nullable();
                $table->json('email_fail')->nullable();
                $table->boolean('email_attach')->default(false);

                $table->timestamps();

                $table->index(['domain_uuid', 'voicemail_uuid']);
                $table->index(['domain_uuid', 'enabled']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('vm_notify_profiles');
    }
};