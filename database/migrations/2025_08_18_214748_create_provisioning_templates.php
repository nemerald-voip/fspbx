<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('provisioning_templates')) {
            Schema::create('provisioning_templates', function (Blueprint $t) {
                $t->uuid('template_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

                // Scope: NULL = visible to all domains (defaults or global customs)
                //        UUID = visible only to that domain (domain-scoped customs)
                $t->uuid('domain_uuid')->nullable()->index();

                $t->string('vendor');               // poly|yealink|grandstream|dinstar
                $t->string('name');                 // display name (e.g., PolyVVX)

                $t->string('type')->default('default');

                // ----- Default releases -----
                $t->string('version')->nullable();  // SemVer string, e.g., "1.0.8" (defaults only)

                // ----- Custom tracking -----
                $t->integer('revision')->default(0);    // bump on every edit of a custom
                $t->string('base_template')->nullable();    // name of default template it came from
                $t->string('base_version')->nullable(); // SemVer of that default at clone time

                // Content + provenance
                $t->text('content');
                $t->string('checksum', 64)->index();   // sha256(content)
                $t->uuid('updated_by')->nullable();

                $t->timestamps();
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('provisioning_templates');
    }
};
