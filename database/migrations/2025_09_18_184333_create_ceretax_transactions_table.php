<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ceretax_transactions')) {
            // Table already exists; nothing to do.
            return;
        }

        Schema::create('ceretax_transactions', function (Blueprint $t) {
            // UUID primary key (you can assign in the model)
            $t->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

            $t->string('invoice_number')->index();   // Stripe invoice id/number
            $t->string('status')->nullable();        // Quote | Active | Posted | ...
            $t->string('ksuid')->nullable()->index();
            $t->string('stan')->nullable()->index(); // systemTraceAuditNumber if you send one
            $t->string('env', 16)->default(config('services.ceretax.env', 'sandbox'))->index();

            // Postgres JSONB columns
            $t->jsonb('request_json');               // full request payload to CereTax
            $t->jsonb('response_json')->nullable();  // full response (success or error)

            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error_summary', 1024)->nullable();

            // TZ-aware timestamps
            $t->timestampsTz(); // creates created_at / updated_at with time zone
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceretax_transactions');
    }
};
