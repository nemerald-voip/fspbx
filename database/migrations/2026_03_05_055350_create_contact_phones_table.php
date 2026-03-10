<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contact_phones')) {
            Schema::create('contact_phones', function (Blueprint $table) {
                // Primary Key (UUID)
                $table->uuid('phone_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                
                // Polymorphic Fields: 'phoneable_type' and 'phoneable_id' (UUID)
                // Allows linking to Contact OR Organization
                $table->uuidMorphs('phoneable'); 
                
                // The Number (Indexed for high-speed chat lookups)
                $table->string('phone_number')->index(); 
                
                // Label (e.g., "Main", "Mobile", "Support Line")
                $table->string('label')->default('work'); 
                
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('phones');
    }
};