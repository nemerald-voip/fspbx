<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('vm_notify_profile_recipients')) {
            Schema::create('vm_notify_profile_recipients', function (Blueprint $table) {
                $table->uuid('vm_notify_profile_recipient_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('vm_notify_profile_uuid')->index();

                $table->string('recipient_type')->nullable();
                $table->uuid('extension_uuid')->nullable()->index();
                $table->string('phone_number')->nullable()->index();
                $table->string('display_name')->nullable();

                $table->unsignedInteger('priority')->nullable()->index();
                $table->unsignedInteger('sort_order')->nullable();
                $table->boolean('enabled')->default(true)->index();

                $table->timestamps();

                $table->index(['vm_notify_profile_uuid', 'priority']);
                $table->index(['vm_notify_profile_uuid', 'enabled']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('vm_notify_profile_recipients');
    }
};