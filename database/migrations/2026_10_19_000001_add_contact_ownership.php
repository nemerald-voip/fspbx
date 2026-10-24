<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'owner_user_uuid')) {
            Schema::table('contacts', function (Blueprint $table) {
                // NULL preserves existing account-shared contacts. Keep the UUID if
                // a user is deleted: SET NULL would publish their private contacts.
                $table->uuid('owner_user_uuid')->nullable();
            });
        }

        // Check independently so a retry also repairs a missing index.
        if (! Schema::hasIndex('contacts', ['domain_uuid', 'owner_user_uuid'])) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index(['domain_uuid', 'owner_user_uuid']);
            });
        }
    }

    public function down(): void
    {
        // Retain ownership metadata on rollback. up() can safely reuse the
        // existing column and index when this migration is applied again.
    }
};
