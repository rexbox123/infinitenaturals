<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\TaxRate;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStripe();
        Mail::fake();
    }

    public function test_checkout_page_shows_summary_shipping_and_payment_options(): void
    {
        $method = $this->shipping();
        $this->addToCart($this->variant(3700, 5), 1);

        $this->get('/checkout')->assertOk()
            ->assertSee('Delivery address')->assertSee($method->name)
            ->assertSee('Pay securely online')->assertSee('$37.00');
    }

    public function test_empty_cart_redirects_to_cart(): void
    {
        $this->get('/checkout')->assertRedirect('/cart');
    }

    public function test_placing_an_order_reserves_stock_and_redirects_to_stripe(): void
    {
        $method = $this->shipping(595, 5000);
        TaxRate::query()->create(['state' => 'FL', 'rate' => 7, 'is_active' => true]);
        $variant = $this->variant(3700, 5);
        $this->addToCart($variant, 1);

        $response = $this->post('/checkout', $this->checkoutData($method, ['expected_total' => 3700 + 595 + 259]));

        $order = Order::query()->with('items')->sole();
        $response->assertRedirect($this->stripe->sessions[$order->stripe_checkout_session_id]['url']);

        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(4554, $order->total);
        $this->assertSame(259, $order->tax_total);
        $this->assertSame(4, $variant->fresh()->stock_quantity, 'stock is held while the customer pays');
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $variant->id, 'quantity_change' => -1, 'reason' => 'order_placed']);
        $this->assertTrue(Cart::query()->first()->items->isEmpty());

        // Stripe session is created for the exact order total with order metadata.
        $params = $this->stripe->created[0];
        $this->assertSame(4554, $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame((string) $order->id, $params['metadata']['order_id']);
        $this->assertSame('jane@example.com', $params['customer_email']);
        Mail::assertNothingQueued(); // no confirmation until payment is confirmed
    }

    public function test_validation_rejects_military_and_non_us_addresses(): void
    {
        $method = $this->shipping();
        $this->addToCart($this->variant());

        $this->post('/checkout', $this->checkoutData($method, ['state' => 'AE']))->assertSessionHasErrors('state');
        $this->post('/checkout', $this->checkoutData($method, ['address_line1' => 'PSC 1234 Box 5678', 'city' => 'APO']))->assertSessionHasErrors('city');
        $this->post('/checkout', $this->checkoutData($method, ['postal_code' => 'ABCDE']))->assertSessionHasErrors('postal_code');
        $this->post('/checkout', $this->checkoutData($method, ['accept_terms' => null]))->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_refused_when_total_changed_since_page_was_shown(): void
    {
        $method = $this->shipping(595, null);
        $variant = $this->variant(2000, 5);
        $this->addToCart($variant);
        $variant->update(['price' => 2500]); // price changed in admin meanwhile

        $this->post('/checkout', $this->checkoutData($method, ['expected_total' => 2595]))
            ->assertRedirect('/checkout')->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $variant->fresh()->stock_quantity);
    }

    public function test_last_unit_cannot_be_sold_twice(): void
    {
        $method = $this->shipping();
        $variant = $this->variant(2000, 1);

        // Two shoppers both have the last unit in their carts.
        $this->addToCart($variant);
        $firstSession = session()->all();
        $this->flushSession();
        $this->addToCart($variant);
        $secondSession = session()->all();

        $this->withSession($firstSession)->post('/checkout', $this->checkoutData($method))->assertRedirect();
        $this->assertSame(0, $variant->fresh()->stock_quantity);

        $this->flushSession();
        $this->withSession($secondSession)->post('/checkout', $this->checkoutData($method, ['email' => 'second@example.com']))
            ->assertRedirect('/checkout')->assertSessionHasErrors(['checkout']);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }

    public function test_gateway_failure_releases_stock_and_returns_items_to_cart(): void
    {
        $this->stripe->failCreate = true;
        $method = $this->shipping();
        $variant = $this->variant(2000, 3);
        $this->addToCart($variant, 2);

        $this->post('/checkout', $this->checkoutData($method))->assertRedirect('/checkout')->assertSessionHasErrors('checkout');

        $order = Order::query()->sole();
        $this->assertSame(PaymentStatus::Cancelled, $order->payment_status);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(2, Cart::query()->first()->items->first()->quantity);
    }

    public function test_checkout_is_closed_without_an_active_shipping_method(): void
    {
        $this->addToCart($this->variant());
        $this->get('/checkout')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_cash_on_delivery_only_when_enabled(): void
    {
        $method = $this->shipping();
        $variant = $this->variant(2000, 3);
        $this->addToCart($variant);

        $this->post('/checkout', $this->checkoutData($method, ['payment_method' => 'cod']))->assertSessionHasErrors('payment_method');

        app(Settings::class)->set('payments.cod_enabled', true);
        $response = $this->post('/checkout', $this->checkoutData($method, ['payment_method' => 'cod']));

        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]));
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled, $order->fulfillment_status);
        Mail::assertQueued(OrderConfirmation::class, fn ($m) => $m->hasTo('jane@example.com'));
    }

    public function test_signed_in_customer_order_is_linked_to_account(): void
    {
        $user = $this->user();
        $method = $this->shipping();
        $this->actingAs($user);
        $this->addToCart($this->variant());

        $this->post('/checkout', $this->checkoutData($method, ['email' => $user->email]));
        $order = Order::query()->sole();
        $this->assertSame($user->id, $order->user_id);
        $this->get('/account')->assertSee($order->number);
    }

    public function test_order_status_page_requires_token(): void
    {
        $method = $this->shipping();
        $this->addToCart($this->variant());
        $this->post('/checkout', $this->checkoutData($method));
        $order = Order::query()->sole();

        $this->get("/orders/{$order->number}")->assertNotFound();
        $this->get("/orders/{$order->number}?token=wrong")->assertNotFound();
        $this->get("/orders/{$order->number}?token={$order->access_token}")->assertOk()->assertSee('Awaiting payment');

        $this->post('/track-order', ['number' => strtolower($order->number), 'email' => 'JANE@example.com'])
            ->assertRedirect(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]));
        $this->post('/track-order', ['number' => $order->number, 'email' => 'other@example.com'])->assertSessionHasErrors('number');
    }
}
