<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update186
{
    private const VERSION = '1.8.6';

    public function apply(): bool
    {
        try {
            $this->ensureCustomerNotesTable();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureCustomerNotesTable(): void
    {
        if (Schema::hasTable('customer_notes')) {
            echo "Customer Notes table already exists.\n";
            return;
        }

        if (Schema::hasTable('customer_information_notes')) {
            Schema::rename('customer_information_notes', 'customer_notes');

            if (Schema::hasColumn('customer_notes', 'customer_information_note_uuid')) {
                Schema::table('customer_notes', function (Blueprint $table) {
                    $table->renameColumn('customer_information_note_uuid', 'customer_note_uuid');
                });
            }

            echo "Renamed legacy notes table to Customer Notes.\n";
            return;
        }

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->uuid('customer_note_uuid')
                ->primary()
                ->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('domain_uuid')->index();
            $table->unsignedTinyInteger('note_level');
            $table->longText('content')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['domain_uuid', 'note_level'], 'customer_notes_domain_level_unique');
        });

        echo "Created Customer Notes table.\n";
    }
}
