<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (!Schema::hasColumn('call_transcription_policy', 'email_transcription')) {
                $table->boolean('email_transcription')
                    ->default(false);
            }

            // Add email if missing
            if (!Schema::hasColumn('call_transcription_policy', 'email')) {
                $table->string('email')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            // Drop only if they exist
            if (Schema::hasColumn('call_transcription_policy', 'email')) {
                $table->dropColumn('email');
            }

            if (Schema::hasColumn('call_transcription_policy', 'email_transcription')) {
                $table->dropColumn('email_transcription');
            }
        });
    }
};
