<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('emails')) {
            Schema::create('emails', function (Blueprint $table) {
                $table->uuid('email_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

                // Creates 'emailable_type' (string) and 'emailable_id' (uuid)
                $table->uuidMorphs('emailable');

                $table->string('email_address')->index();
                $table->string('label')->default('work');

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('emails');
    }
};
