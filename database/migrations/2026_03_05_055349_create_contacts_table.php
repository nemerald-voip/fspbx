<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->uuid('contact_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();

                $table->uuid('organization_uuid')->nullable()->index();

                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('title')->nullable();
                $table->string('department')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};
