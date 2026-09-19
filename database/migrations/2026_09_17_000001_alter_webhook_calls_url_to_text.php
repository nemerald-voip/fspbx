<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getColumnType('webhook_calls', 'url') === 'text') {
            return;
        }

        // GET callbacks can include the SMS body in the query string.
        DB::statement('ALTER TABLE webhook_calls ALTER COLUMN url TYPE text');
    }

    public function down(): void
    {
        // Let PostgreSQL reject the rollback if a stored URL exceeds 255 characters.
        DB::statement('ALTER TABLE webhook_calls ALTER COLUMN url TYPE varchar(255)');
    }
};
