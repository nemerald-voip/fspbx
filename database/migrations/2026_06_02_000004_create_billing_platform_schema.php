<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) {
            DB::statement('ALTER TABLE organizations ALTER COLUMN domain_uuid DROP NOT NULL');

            Schema::table('organizations', function (Blueprint $table) {
                if (! Schema::hasColumn('organizations', 'billing_provider')) {
                    $table->string('billing_provider')->default('stripe')->index();
                }
                if (! Schema::hasColumn('organizations', 'billing_provider_customer_id')) {
                    $table->string('billing_provider_customer_id')->nullable()->index();
                }
                if (! Schema::hasColumn('organizations', 'billing_livemode')) {
                    $table->boolean('billing_livemode')->default(false)->index();
                }
                if (! Schema::hasColumn('organizations', 'billing_enabled')) {
                    $table->boolean('billing_enabled')->default(false)->index();
                }
                if (! Schema::hasColumn('organizations', 'billing_synced_at')) {
                    $table->timestampTz('billing_synced_at')->nullable();
                }
                if (! Schema::hasColumn('organizations', 'billing_last_sync_error')) {
                    $table->text('billing_last_sync_error')->nullable();
                }
                if (! Schema::hasColumn('organizations', 'billing_metadata')) {
                    $table->jsonb('billing_metadata')->nullable();
                }
                if (! Schema::hasColumn('organizations', 'billing_invoice_prefix')) {
                    $table->string('billing_invoice_prefix', 32)->nullable();
                }
                if (! Schema::hasColumn('organizations', 'billing_next_invoice_sequence')) {
                    $table->unsignedBigInteger('billing_next_invoice_sequence')->nullable();
                }
            });

            DB::table('organizations')
                ->where('billing_enabled', true)
                ->update(['domain_uuid' => null]);
        }

        if (Schema::hasTable('contact_addresses')) {
            DB::statement('ALTER TABLE contact_addresses ALTER COLUMN domain_uuid DROP NOT NULL');

            if (Schema::hasTable('organizations')) {
                DB::table('contact_addresses')
                    ->where('addressable_type', 'App\\Models\\Organization')
                    ->whereIn('addressable_id', function ($query) {
                        $query->select('organization_uuid')
                            ->from('organizations')
                            ->where('billing_enabled', true);
                    })
                    ->update(['domain_uuid' => null]);
            }
        }

        if (Schema::hasTable('billing_products')) {
            Schema::table('billing_products', function (Blueprint $table) {
                if (! Schema::hasColumn('billing_products', 'synced_at')) {
                    $table->timestampTz('synced_at')->nullable();
                }
                if (! Schema::hasColumn('billing_products', 'last_sync_error')) {
                    $table->text('last_sync_error')->nullable();
                }
            });
        }

        if (Schema::hasTable('billing_prices')) {
            if (Schema::hasColumn('billing_prices', 'unit_amount_cents')) {
                DB::statement('ALTER TABLE billing_prices ALTER COLUMN unit_amount_cents DROP NOT NULL');
            }

            Schema::table('billing_prices', function (Blueprint $table) {
                if (! Schema::hasColumn('billing_prices', 'billing_scheme')) {
                    $table->string('billing_scheme')->default('per_unit')->index();
                }
                if (! Schema::hasColumn('billing_prices', 'tiers_mode')) {
                    $table->string('tiers_mode')->nullable();
                }
                if (! Schema::hasColumn('billing_prices', 'tiers')) {
                    $table->jsonb('tiers')->nullable();
                }
                if (! Schema::hasColumn('billing_prices', 'lookup_key')) {
                    $table->string('lookup_key')->nullable()->index();
                }
                if (! Schema::hasColumn('billing_prices', 'tax_behavior')) {
                    $table->string('tax_behavior')->nullable();
                }
                if (! Schema::hasColumn('billing_prices', 'recurring_usage_type')) {
                    $table->string('recurring_usage_type')->nullable();
                }
                if (! Schema::hasColumn('billing_prices', 'transform_quantity')) {
                    $table->jsonb('transform_quantity')->nullable();
                }
            });
        } else {
            Schema::create('billing_prices', function (Blueprint $table) {
                $table->uuid('price_uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('product_uuid')->index();
                $table->string('provider')->default('stripe')->index();
                $table->string('provider_price_id')->nullable()->index();
                $table->boolean('livemode')->default(false)->index();
                $table->boolean('active')->default(true)->index();
                $table->string('currency', 3)->default('usd');
                $table->bigInteger('unit_amount_cents')->nullable();
                $table->string('billing_scheme')->default('per_unit')->index();
                $table->string('tiers_mode')->nullable();
                $table->jsonb('tiers')->nullable();
                $table->string('line_type')->default('one_time')->index();
                $table->string('interval')->nullable();
                $table->unsignedInteger('interval_count')->nullable();
                $table->string('nickname')->nullable();
                $table->string('lookup_key')->nullable()->index();
                $table->string('tax_behavior')->nullable();
                $table->string('recurring_usage_type')->nullable();
                $table->jsonb('transform_quantity')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampTz('synced_at')->nullable();
                $table->text('last_sync_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_prices');

        if (Schema::hasTable('billing_products')) {
            Schema::table('billing_products', function (Blueprint $table) {
                foreach (['synced_at', 'last_sync_error'] as $column) {
                    if (Schema::hasColumn('billing_products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('organizations')) {
            $hasGlobalOrganizations = DB::table('organizations')
                ->whereNull('domain_uuid')
                ->exists();

            if (! $hasGlobalOrganizations) {
                DB::statement('ALTER TABLE organizations ALTER COLUMN domain_uuid SET NOT NULL');
            }

            Schema::table('organizations', function (Blueprint $table) {
                foreach ([
                    'billing_provider',
                    'billing_provider_customer_id',
                    'billing_livemode',
                    'billing_enabled',
                    'billing_synced_at',
                    'billing_last_sync_error',
                    'billing_metadata',
                    'billing_invoice_prefix',
                    'billing_next_invoice_sequence',
                ] as $column) {
                    if (Schema::hasColumn('organizations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('contact_addresses')) {
            $hasGlobalAddresses = DB::table('contact_addresses')
                ->whereNull('domain_uuid')
                ->exists();

            if (! $hasGlobalAddresses) {
                DB::statement('ALTER TABLE contact_addresses ALTER COLUMN domain_uuid SET NOT NULL');
            }
        }
    }
};
