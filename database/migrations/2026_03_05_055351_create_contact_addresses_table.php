<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contact_addresses')) {
            Schema::create('contact_addresses', function (Blueprint $table) {
                $table->uuid('address_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();

                // Creates 'addressable_type' (string) and 'addressable_id' (uuid)
                $table->uuidMorphs('addressable');

                $table->string('label')->default('main');
                $table->string('street')->nullable();
                $table->string('extended')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country_code')->default('US');

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contact_addresses');
    }
};
