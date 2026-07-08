<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('device_phonebook')) {
            return;
        }

        DB::statement('ALTER TABLE device_phonebook REPLICA IDENTITY FULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('device_phonebook')) {
            return;
        }

        DB::statement('ALTER TABLE device_phonebook REPLICA IDENTITY DEFAULT');
    }
};
