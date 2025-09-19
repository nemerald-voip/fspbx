<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_products')) {
            Schema::create('billing_products', function (Blueprint $table) {
                // Generic PK (uuid) so we’re not tied to any one provider
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));

                // Provider-agnostic identifiers
                $table->string('provider')->index();             // e.g. 'stripe'
                $table->string('provider_product_id')->index();  // e.g. 'prod_...'
                $table->string('default_price_ref')->nullable(); // e.g. 'price_...' (generic)

                // Common fields
                $table->boolean('livemode')->default(true)->index();
                $table->boolean('active')->default(true);
                $table->string('name')->index();
                $table->text('description')->nullable();
                $table->string('type')->nullable();       
                $table->string('statement_descriptor')->nullable();
                $table->string('unit_label')->nullable();
                $table->string('url')->nullable();

                // JSONB blobs
                $table->jsonb('metadata')->nullable();
                $table->jsonb('images')->nullable();
                $table->jsonb('marketing_features')->nullable();
                $table->jsonb('package_dimensions')->nullable();

                // Nullable booleans / provider timestamps
                $table->boolean('shippable')->nullable();
                $table->timestampTz('external_created_at')->nullable();
                $table->timestampTz('external_updated_at')->nullable();

                // Bookkeeping
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_products');
    }
};
