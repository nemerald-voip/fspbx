<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable domain_uuid to personal_access_tokens so a token can be
 * scoped to a single tenant. A null domain_uuid is a global/admin token.
 *
 * This migration is shared with PR #422 (which introduces the same column for
 * the CDR API). Whichever merges first wins — the migration is idempotent
 * (hasColumn guard) so running it twice is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'domain_uuid')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('domain_uuid')->nullable()->after('abilities');
            $table->index('domain_uuid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'domain_uuid')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['domain_uuid']);
            $table->dropColumn('domain_uuid');
        });
    }
};
