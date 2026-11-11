<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Payments\StripeApi;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeStripeApi;

trait BuildsStore
{
    protected FakeStripeApi $stripe;

    protected function fakeStripe(): FakeStripeApi
    {
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.key' => 'pk_test_fake',
            'services.stripe.webhook_secret' => 'whsec_test_secret',
        ]);
        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);

        return $this->stripe;
    }

    protected function variant(int $price = 2000, int $stock = 10, array $attributes = []): ProductVariant
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => $price] + $attributes);
        $variant->forceFill(['stock_quantity' => $stock])->save();

        return $variant->fresh('product');
    }

    protected function shipping(int $rate = 595, ?int $freeOver = 5000): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'name' => 'USPS Standard', 'rate' => $rate, 'free_over' => $freeOver, 'is_active' => true,
        ]);
    }

    protected function addToCart(ProductVariant $variant, int $quantity = 1): void
    {
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => $quantity])->assertSessionHasNoErrors()->assertRedirect();
    }

    protected function checkoutData(ShippingMethod $method, array $overrides = []): array
    {
        return array_merge([
            'email' => 'jane@example.com',
            'phone' => '352-555-0100',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_line1' => '100 Main St',
            'city' => 'Ocala',
            'state' => 'FL',
            'postal_code' => '34480',
            'shipping_method_id' => $method->id,
            'payment_method' => 'stripe',
            'accept_terms' => '1',
        ], $overrides);
    }

    protected function user(string $role = 'customer'): User
    {
        return User::factory()->role($role)->create();
    }

    /** Build a Stripe-Signature header exactly like Stripe does. */
    protected function signedWebhook(array $event): TestResponse
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", config('services.stripe.webhook_secret'));

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function sessionEvent(string $type, array $session, ?string $id = null): array
    {
        return [
            'id' => $id ?? 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $session],
        ];
    }
}
