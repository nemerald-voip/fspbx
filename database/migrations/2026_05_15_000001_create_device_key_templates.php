<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_key_templates')) {
            Schema::create('device_key_templates', function (Blueprint $table) {
                $table->uuid('device_key_template_uuid')
                    ->primary()
                    ->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('enabled', 5)->default('true');
                $table->timestamps();

                $table->index(['domain_uuid', 'name'], 'device_key_templates_domain_name_idx');
            });
        }

        if (! Schema::hasTable('device_key_template_keys')) {
            Schema::create('device_key_template_keys', function (Blueprint $table) {
                $table->uuid('device_key_template_key_uuid')
                    ->primary()
                    ->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('device_key_template_uuid')->index();
                $table->string('key_area', 50)->nullable()->default('main');
                $table->unsignedSmallInteger('key_index')->nullable();
                $table->string('key_type', 50)->nullable();
                $table->string('key_value', 64)->nullable();
                $table->string('key_label', 80)->nullable();
                $table->timestamps();

                $table->unique(
                    ['device_key_template_uuid', 'key_area', 'key_index'],
                    'device_key_template_keys_area_index_unique'
                );
            });
        }

        if (! Schema::hasColumn('v_devices', 'device_key_template_uuid')) {
            Schema::table('v_devices', function (Blueprint $table) {
                $table->uuid('device_key_template_uuid')->nullable();
                $table->index('device_key_template_uuid', 'v_devices_device_key_template_uuid_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_devices', 'device_key_template_uuid')) {
            Schema::table('v_devices', function (Blueprint $table) {
                try {
                    $table->dropIndex('v_devices_device_key_template_uuid_idx');
                } catch (Throwable $e) {
                    //
                }

                $table->dropColumn('device_key_template_uuid');
            });
        }

        Schema::dropIfExists('device_key_template_keys');
        Schema::dropIfExists('device_key_templates');
    }
};
