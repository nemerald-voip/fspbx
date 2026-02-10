<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_keys')) {
            return;
        }

        Schema::create('device_keys', function (Blueprint $table) {
            $table->uuid('device_key_uuid')
                ->primary()
                ->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('device_uuid')->index()->nullable();

            $table->unsignedSmallInteger('key_index')->nullable();

            $table->string('key_type', 50)->nullable();

            $table->string('key_value', 64)->nullable();

            $table->string('key_label', 80)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_keys');
    }
};
