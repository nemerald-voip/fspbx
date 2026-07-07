<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('phonebooks')) {
            Schema::create('phonebooks', function (Blueprint $table) {
                $table->uuid('phonebook_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();

                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true);
                // Marks a phonebook as part of the account default set, pushed to
                // devices set to "Use account default".
                $table->boolean('is_default')->default(false);
                // When true, the account's internal extensions are included in
                // the directory (alongside the shared CRM contacts).
                $table->boolean('include_extensions')->default(true);

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('phonebook_contacts')) {
            Schema::create('phonebook_contacts', function (Blueprint $table) {
                $table->uuid('phonebook_contact_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('phonebook_uuid')->index();
                $table->uuid('domain_uuid')->index();

                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone_number');
                $table->integer('sort_order')->default(0);

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('device_phonebook')) {
            Schema::create('device_phonebook', function (Blueprint $table) {
                $table->uuid('device_uuid')->index();
                $table->uuid('phonebook_uuid')->index();
                $table->integer('slot')->default(1);

                $table->timestamps();

                $table->unique(['device_uuid', 'phonebook_uuid']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('device_phonebook');
        Schema::dropIfExists('phonebook_contacts');
        Schema::dropIfExists('phonebook_sources'); // legacy table, dropped in simplification
        Schema::dropIfExists('phonebooks');
    }
};
