<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flags')) {
            Schema::create('flags', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->string('name');
                // BLF and toggle address is flag<flag_number>@domain.
                $table->string('flag_number', 20);
                // manual: toggled by people. scheduled: active while a rule matches.
                $table->string('type', 20)->default('manual');
                $table->string('timezone')->nullable();
                // Manual state. Scheduled flags compute their state from rules.
                $table->boolean('active')->default(false);
                $table->string('pin_number', 20)->nullable();
                $table->boolean('blf_enabled')->default(false);
                $table->boolean('enabled')->default(true);
                $table->string('description')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'flag_number']);
            });
        }

        if (!Schema::hasTable('flag_rules')) {
            Schema::create('flag_rules', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('flag_uuid')->index();
                // weekly: weekdays + start/end time. date_range: start/end date with optional times.
                $table->string('rule_type', 20);
                // Comma-separated FreeSWITCH wday values (1=Sun ... 7=Sat).
                $table->string('weekdays', 20)->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->foreign('flag_uuid')->references('uuid')->on('flags')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('flag_extensions')) {
            Schema::create('flag_extensions', function (Blueprint $table) {
                $table->uuid('flag_uuid');
                $table->uuid('extension_uuid');

                $table->primary(['flag_uuid', 'extension_uuid']);
                $table->foreign('flag_uuid')->references('uuid')->on('flags')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_extensions');
        Schema::dropIfExists('flag_rules');
        Schema::dropIfExists('flags');
    }
};
