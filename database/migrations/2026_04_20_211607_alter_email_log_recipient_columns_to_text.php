<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE email_log ALTER COLUMN "to" TYPE text');
        DB::statement('ALTER TABLE email_log ALTER COLUMN "cc" TYPE text');
        DB::statement('ALTER TABLE email_log ALTER COLUMN "bcc" TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE email_log ALTER COLUMN "to" TYPE varchar(255)');
        DB::statement('ALTER TABLE email_log ALTER COLUMN "cc" TYPE varchar(255)');
        DB::statement('ALTER TABLE email_log ALTER COLUMN "bcc" TYPE varchar(255)');
    }
};
