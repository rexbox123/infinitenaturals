<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderNotification;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    private ProductVariant $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStripe();
        Mail::fake();
    }

    private function placeOrder(int $stock = 5, int $qty = 2): Order
    {
        $method = $this->shipping(595, 5000);
        $this->item = $this->variant(2000, $stock);
        $this->addToCart($this->item, $qty);
        $this->post('/checkout', $this->checkoutData($method))->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function returnUrl(Order $order): string
    {
        return URL::signedRoute('checkout.stripe.return', ['order' => $order->number]).'&session_id='.$order->stripe_checkout_session_id;
    }

    public function test_success_return_is_verified_with_stripe_before_marking_paid(): void
    {
        $order = $this->placeOrder();

        // Customer lands on the success URL without having paid: must NOT be marked paid.
        $this->get($this->returnUrl($order))->assertRedirect();
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);

        $this->stripe->complete($order->stripe_checkout_session_id);
        $this->get($this->returnUrl($order))
            ->assertRedirect(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'payment was successful'));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertStringStartsWith('pi_test_', $order->stripe_payment_intent_id);
        Mail::assertQueued(OrderConfirmation::class, 1);
        Mail::assertQueued(NewOrderNotification::class, 1);

        // Revisiting is harmless.
        $this->get($this->returnUrl($order))->assertRedirect();
        Mail::assertQueued(OrderConfirmation::class, 1);
    }

    public function test_return_url_rejects_tampering(): void
    {
        $order = $this->placeOrder();
        $this->get(route('checkout.stripe.return', ['order' => $order->number]).'?session_id='.$order->stripe_checkout_session_id)->assertForbidden();
        $this->get(URL::signedRoute('checkout.stripe.return', ['order' => $order->number]).'&session_id=cs_other')->assertNotFound();
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $order = $this->placeOrder();
        $this->stripe->complete($order->stripe_checkout_session_id);
        $event = $this->sessionEvent('checkout.session.completed', $this->stripe->sessions[$order->stripe_checkout_session_id]);

        $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad', 'CONTENT_TYPE' => 'application/json'], json_encode($event))
            ->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_webhook_marks_order_paid_once(): void
    {
        $order = $this->placeOrder();
        $session = $this->stripe->complete($order->stripe_checkout_session_id);
        $event = $this->sessionEvent('checkout.session.completed', $session, 'evt_paid_1');

        $this->signedWebhook($event)->assertOk();
        $this->signedWebhook($event)->assertOk()->assertSee('Already processed');

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        Mail::assertQueued(OrderConfirmation::class, 1);
        $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_paid_1']);
    }

    public function test_amount_mismatch_is_flagged_not_paid(): void
    {
        $order = $this->placeOrder();
        $session = $this->stripe->complete($order->stripe_checkout_session_id, true, 100);

        $this->signedWebhook($this->sessionEvent('checkout.session.completed', $session))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertTrue($order->needs_review);
    }

    public function test_delayed_payment_goes_pending_then_paid(): void
    {
        $order = $this->placeOrder();
        $session = $this->stripe->complete($order->stripe_checkout_session_id, paid: false);

        $this->signedWebhook($this->sessionEvent('checkout.session.completed', $session))->assertOk();
        $this->assertSame(PaymentStatus::Processing, $order->fresh()->payment_status);
        $this->get(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]))->assertSee('Your payment is processing');
        Mail::assertQueued(OrderConfirmation::class, 1);

        $paid = array_merge($session, ['payment_status' => 'paid']);
        $this->signedWebhook($this->sessionEvent('checkout.session.async_payment_succeeded', $paid))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        Mail::assertQueued(OrderConfirmation::class, 1); // still one email
    }

    public function test_delayed_payment_failure_releases_stock(): void
    {
        $order = $this->placeOrder(stock: 5, qty: 2);
        $this->assertSame(3, $this->item->fresh()->stock_quantity);
        $session = $this->stripe->complete($order->stripe_checkout_session_id, paid: false);
        $this->signedWebhook($this->sessionEvent('checkout.session.completed', $session))->assertOk();

        $this->signedWebhook($this->sessionEvent('checkout.session.async_payment_failed', $session))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Cancelled, $order->fulfillment_status);
        $this->assertSame(5, $this->item->fresh()->stock_quantity);
        $this->get(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]))
            ->assertSee('Your payment did not go through')->assertSee('Try again');
    }

    public function test_expired_session_cancels_order_and_releases_stock_once(): void
    {
        $order = $this->placeOrder(stock: 5, qty: 2);
        $session = array_merge($this->stripe->sessions[$order->stripe_checkout_session_id], ['status' => 'expired']);

        $this->signedWebhook($this->sessionEvent('checkout.session.expired', $session, 'evt_exp'))->assertOk();
        $this->signedWebhook($this->sessionEvent('checkout.session.expired', $session, 'evt_exp_retry'))->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, $order->fresh()->payment_status);
        $this->assertSame(5, $this->item->fresh()->stock_quantity, 'released exactly once');
    }

    public function test_customer_cancel_expires_session_restores_cart_and_stock(): void
    {
        $order = $this->placeOrder(stock: 5, qty: 2);

        $this->get(URL::signedRoute('checkout.stripe.cancel', ['order' => $order->number]))
            ->assertRedirect('/cart')->assertSessionHas('status', fn ($s) => str_contains($s, 'not been charged'));

        $this->assertSame('expired', $this->stripe->sessions[$order->stripe_checkout_session_id]['status']);
        $this->assertSame(PaymentStatus::Cancelled, $order->fresh()->payment_status);
        $this->assertSame(5, $this->item->fresh()->stock_quantity);
        $this->assertSame(2, Cart::query()->latest('id')->first()->items->first()->quantity);
    }

    public function test_cancel_after_payment_does_not_cancel_paid_order(): void
    {
        $order = $this->placeOrder();
        $this->stripe->complete($order->stripe_checkout_session_id);

        $this->get(URL::signedRoute('checkout.stripe.cancel', ['order' => $order->number]))->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_retry_after_failure_refills_cart(): void
    {
        $order = $this->placeOrder(stock: 5, qty: 2);
        $this->get(URL::signedRoute('checkout.stripe.cancel', ['order' => $order->number]));
        Cart::query()->first()->items()->delete();

        $this->post(route('orders.retry', $order), ['token' => 'wrong'])->assertForbidden();
        $this->post(route('orders.retry', $order), ['token' => $order->access_token])->assertRedirect('/checkout');
        $this->assertSame(2, Cart::query()->latest('id')->first()->items->first()->quantity);
    }

    public function test_resume_payment_redirects_to_open_session(): void
    {
        $order = $this->placeOrder();
        $this->post(route('orders.pay', $order), ['token' => $order->access_token])
            ->assertRedirect($this->stripe->sessions[$order->stripe_checkout_session_id]['url']);
    }

    public function test_scheduled_cleanup_cancels_abandoned_orders_but_keeps_paid_ones(): void
    {
        $abandoned = $this->placeOrder(stock: 5, qty: 1);
        $paidLate = $this->placeOrder(stock: 5, qty: 1);
        $this->stripe->complete($paidLate->stripe_checkout_session_id);
        Order::query()->update(['payment_expires_at' => now()->subHour()]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(PaymentStatus::Cancelled, $abandoned->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Paid, $paidLate->fresh()->payment_status);
    }

    public function test_refund_webhook_updates_order(): void
    {
        $order = $this->placeOrder();
        $session = $this->stripe->complete($order->stripe_checkout_session_id);
        $this->signedWebhook($this->sessionEvent('checkout.session.completed', $session));
        $order->refresh();

        $this->signedWebhook([
            'id' => 'evt_ref', 'type' => 'charge.refunded',
            'data' => ['object' => ['object' => 'charge', 'payment_intent' => $order->stripe_payment_intent_id, 'amount_refunded' => 1000]],
        ])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(1000, $order->amount_refunded);
    }

    public function test_webhook_without_configured_secret_fails_closed(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        $this->postJson(route('webhooks.stripe'), ['type' => 'checkout.session.completed'])->assertStatus(500);
    }
}
