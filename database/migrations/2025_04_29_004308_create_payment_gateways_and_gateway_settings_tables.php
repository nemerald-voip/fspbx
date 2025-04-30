<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create payment_gateways table if it doesn't exist
        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
                $table->string('slug', 50)->unique()->comment('e.g. stripe, paypal');
                $table->string('name', 100)->comment('Display name, e.g. Stripe, PayPal');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        // Create gateway_settings table if it doesn't exist
        if (!Schema::hasTable('gateway_settings')) {
            Schema::create('gateway_settings', function (Blueprint $table) {
                $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('gateway_uuid');
                $table->string('setting_key', 100)->comment('Key, e.g. secret_key, webhook_secret');
                $table->text('setting_value')->nullable()->comment('Encrypted or plaintext value');
                $table->timestamps();

            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gateway_settings');
        Schema::dropIfExists('payment_gateways');
    }
};

