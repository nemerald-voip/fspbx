<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModifyMigrationsTableToUseUuids extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('migrations', function (Blueprint $table) {
            // Add a new UUID column
            $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'))->after('id');
        });

        // Update existing records with UUIDs
        DB::table('migrations')->get()->each(function ($migration) {
            DB::table('migrations')
                ->where('id', $migration->id)
                ->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('migrations', function (Blueprint $table) {
            // Drop the existing primary key
            $table->dropPrimary(['id']);
            
            // Drop the old 'id' column
            $table->dropColumn('id');
            
            // Rename 'uuid' column to 'id'
            $table->renameColumn('uuid', 'id');
            
            // Set the 'id' column as primary key
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('migrations', function (Blueprint $table) {
            // Drop the primary key
            $table->dropPrimary(['id']);
            
            // Rename 'id' column back to 'uuid'
            $table->renameColumn('id', 'uuid');
            
            // Add the old 'id' column back
            $table->increments('id')->first();
            
            // Drop the 'uuid' column
            $table->dropColumn('uuid');
            
            // Set the 'id' column as primary key
            $table->primary('id');
        });
    }
}

