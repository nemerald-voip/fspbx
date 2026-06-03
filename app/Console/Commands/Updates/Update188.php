<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update188
{
    private const VERSION = '1.8.8';

    public function apply(): bool
    {
        try {
            $this->ensureEmailLogProviderColumns();

            echo 'Update ' . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureEmailLogProviderColumns(): void
    {
        if (! Schema::hasTable('email_log')) {
            echo "Email log table not found; skipping provider lookup columns.\n";
            return;
        }

        Schema::table('email_log', function (Blueprint $table) {
            if (! Schema::hasColumn('email_log', 'provider')) {
                $table->string('provider')->nullable();
            }

            if (! Schema::hasColumn('email_log', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable();
            }

            if (! Schema::hasColumn('email_log', 'provider_message_stream')) {
                $table->string('provider_message_stream')->nullable();
            }
        });

        echo "Ensured email log provider lookup columns exist.\n";
    }
}
