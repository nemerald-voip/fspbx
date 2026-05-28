<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_information_notes') && ! Schema::hasTable('customer_notes')) {
            Schema::rename('customer_information_notes', 'customer_notes');

            if (Schema::hasColumn('customer_notes', 'customer_information_note_uuid')) {
                Schema::table('customer_notes', function (Blueprint $table) {
                    $table->renameColumn('customer_information_note_uuid', 'customer_note_uuid');
                });
            }

            return;
        }

        if (! Schema::hasTable('customer_notes')) {
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
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
