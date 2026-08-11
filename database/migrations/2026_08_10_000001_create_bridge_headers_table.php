<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bridge_headers')) {
            return;
        }

        Schema::create('bridge_headers', function (Blueprint $table) {
            $table->uuid('bridge_header_uuid')->primary();
            $table->uuid('bridge_uuid')->index();
            $table->uuid('domain_uuid')->index();
            $table->string('header_name', 255);
            $table->text('header_value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bridge_uuid', 'header_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_headers');
    }
};
