<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMobileAppPasswordResetLinksTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('mobile_app_password_reset_links')) {
            Schema::create('mobile_app_password_reset_links', function (Blueprint $table) {
                $table->uuid('link_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('extension_uuid')->nullable();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_app_password_reset_links');
    }
};
