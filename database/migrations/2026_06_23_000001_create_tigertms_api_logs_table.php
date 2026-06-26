<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tigertms_api_logs')) {
            return;
        }

        Schema::create('tigertms_api_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('domain_uuid')->nullable()->index();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->text('url')->nullable();
            $table->json('request_context')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable()->index();
            $table->json('response_body')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tigertms_api_logs');
    }
};
