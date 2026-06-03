<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            if (! Schema::hasColumn('email_log', 'provider')) {
                $table->string('provider')->nullable();
            }

            if (! Schema::hasColumn('email_log', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable();
            }

            if (! Schema::hasColumn('email_log', 'provider_message_stream')) {
                $table->string('provider_message_stream')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            foreach (['provider_message_stream', 'provider_message_id', 'provider'] as $column) {
                if (Schema::hasColumn('email_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
