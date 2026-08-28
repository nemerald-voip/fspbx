<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dynamic_routes')) {
            Schema::create('dynamic_routes', function (Blueprint $table) {
                $table->uuid('dynamic_route_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid');
                $table->uuid('dialplan_uuid')->unique();
                $table->string('name');
                $table->string('extension');
                $table->string('source')->default('caller_destination');
                $table->string('context');
                $table->string('default_destination_type');
                $table->text('default_destination_value')->nullable();
                $table->string('default_destination_label')->nullable();
                $table->boolean('enabled')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'extension']);
                $table->index(['domain_uuid', 'name']);
            });
        }

        if (! Schema::hasTable('dynamic_route_rules')) {
            Schema::create('dynamic_route_rules', function (Blueprint $table) {
                $table->uuid('dynamic_route_rule_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('dynamic_route_uuid');
                $table->string('match_value');
                $table->string('destination_type');
                $table->text('destination_value')->nullable();
                $table->string('destination_label')->nullable();
                $table->unsignedInteger('rule_order')->default(0);
                $table->timestamps();

                $table->index(['dynamic_route_uuid', 'rule_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_route_rules');
        Schema::dropIfExists('dynamic_routes');
    }
};
