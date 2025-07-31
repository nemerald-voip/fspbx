<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only add column if it doesn't exist
        if (! $this->columnExists('messages', 'media')) {
            // Change 'text' to 'json' if you want a json field instead
            Schema::table('messages', function (Blueprint $table) {
                $table->text('media')->nullable();
            });
        }
    }

    public function down(): void
    {
        if ($this->columnExists('messages', 'media')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('media');
            });
        }
    }

    protected function columnExists($table, $column)
    {
        // This works for PostgreSQL
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
};
