<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('rate')->default(0);
            // Merchandise total (after discounts) at or above which this method is free. Null = never free.
            $table->unsignedInteger('free_over')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->unique();
            // Percentage, e.g. 7.0000 = 7%.
            $table->decimal('rate', 7, 4);
            $table->boolean('applies_to_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();
            // percent | fixed | free_shipping
            $table->string('type', 20);
            // Percent (1-100) for "percent", cents for "fixed", ignored for "free_shipping".
            $table->unsignedInteger('value')->default(0);
            $table->unsignedInteger('min_subtotal')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('access_token', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state', 2);
            $table->string('postal_code', 10);
            $table->string('country', 2)->default('US');

            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_method_name')->nullable();
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discount_code', 50)->nullable();

            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('tax_total')->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->unsignedInteger('total');
            $table->string('currency', 3)->default('USD');

            // stripe | cod
            $table->string('payment_method', 20);
            // pending | processing | paid | failed | cancelled | refunded | partially_refunded
            $table->string('payment_status', 30)->default('pending')->index();
            // unfulfilled | processing | shipped | delivered | cancelled
            $table->string('fulfillment_status', 30)->default('unfulfilled')->index();

            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->timestamp('payment_expires_at')->nullable();
            $table->unsignedInteger('amount_refunded')->default(0);

            $table->string('tracking_carrier', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();

            $table->text('customer_note')->nullable();
            $table->boolean('needs_review')->default(false);

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total');
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->text('message');
            $table->boolean('is_customer_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            // order_placed | order_released | restock | adjustment | return | initial
            $table->string('reason', 30);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id');
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_events', 'stock_movements', 'order_events', 'order_items', 'orders', 'cart_items', 'carts', 'discount_codes', 'tax_rates', 'shipping_methods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
