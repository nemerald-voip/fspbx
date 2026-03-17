<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('vm_notify_logs')) {
            Schema::create('vm_notify_logs', function (Blueprint $table) {
                $table->uuid('vm_notify_log_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('vm_notify_notification_uuid')->index();

                $table->string('level')->default('info')->index();
                $table->text('message');
                $table->json('context')->nullable();

                $table->timestamps();

                $table->index(['vm_notify_notification_uuid', 'level']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('vm_notify_logs');
    }
};