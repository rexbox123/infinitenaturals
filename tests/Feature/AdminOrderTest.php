<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStripe();
        Mail::fake();
    }

    private function paidOrder(int $stock = 5): Order
    {
        $method = $this->shipping();
        $this->item = $this->variant(3000, $stock);
        $this->addToCart($this->item, 2);
        $this->post('/checkout', $this->checkoutData($method));
        $order = Order::query()->latest('id')->first();
        $this->stripe->complete($order->stripe_checkout_session_id);
        app(\App\Services\Payments\StripeCheckout::class)->refresh($order);
        $this->flushSession();

        return $order->fresh();
    }

    public function test_staff_ships_order_with_tracking_and_customer_is_emailed(): void
    {
        $order = $this->paidOrder();
        $staff = $this->user('staff');

        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk()->assertSee($order->number)->assertSee('Paid');

        $this->actingAs($staff)->patch(route('admin.orders.fulfillment', $order), [
            'fulfillment_status' => 'shipped', 'tracking_carrier' => 'usps', 'tracking_number' => '9400111899223', 'notify_customer' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order->refresh();
        $this->assertSame(FulfillmentStatus::Shipped, $order->fulfillment_status);
        $this->assertNotNull($order->shipped_at);
        $this->assertStringContainsString('9400111899223', $order->trackingLink());
        Mail::assertQueued(OrderShipped::class, fn ($m) => $m->hasTo($order->email));

        $this->get(route('orders.show', ['order' => $order->number, 'token' => $order->access_token]))
            ->assertSee('Shipped')->assertSee('9400111899223')->assertSee('Track package');
    }

    public function test_unpaid_card_orders_cannot_be_fulfilled(): void
    {
        $method = $this->shipping();
        $this->addToCart($this->variant());
        $this->post('/checkout', $this->checkoutData($method));
        $order = Order::query()->sole();

        $this->actingAs($this->user('admin'))->patch(route('admin.orders.fulfillment', $order), ['fulfillment_status' => 'shipped'])
            ->assertSessionHasErrors('fulfillment_status');
    }

    public function test_cancelling_an_unpaid_order_expires_payment_and_restocks(): void
    {
        $method = $this->shipping();
        $variant = $this->variant(1000, 4);
        $this->addToCart($variant, 3);
        $this->post('/checkout', $this->checkoutData($method));
        $order = Order::query()->sole();
        $this->assertSame(1, $variant->fresh()->stock_quantity);

        $this->actingAs($this->user('admin'))->post(route('admin.orders.cancel', $order), ['reason' => 'Customer request', 'restock' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $order->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Cancelled, $order->fulfillment_status);
        $this->assertSame('expired', $this->stripe->sessions[$order->stripe_checkout_session_id]['status']);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }

    public function test_paid_orders_must_be_refunded_before_cancelling(): void
    {
        $order = $this->paidOrder();
        $manager = $this->user('manager');

        $this->actingAs($manager)->post(route('admin.orders.cancel', $order), ['restock' => '1'])->assertSessionHasErrors('cancel');

        $this->actingAs($manager)->post(route('admin.orders.refund', $order), ['restock' => '1', 'reason' => 'Damaged'])->assertSessionHasNoErrors()->assertRedirect();
        $order->refresh();
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame($order->total, $order->amount_refunded);
        $this->assertSame($order->stripe_payment_intent_id, $this->stripe->refunds[0]['payment_intent']);
        $this->assertSame(5, $this->item->fresh()->stock_quantity);
    }

    public function test_staff_cannot_refund(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->user('staff'))->post(route('admin.orders.refund', $order))->assertForbidden();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_card_orders_cannot_be_marked_paid_manually_but_cod_can(): void
    {
        $method = $this->shipping();
        $this->addToCart($this->variant());
        $this->post('/checkout', $this->checkoutData($method));
        $card = Order::query()->sole();
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.orders.cash-collected', $card))->assertSessionHasErrors('payment');
        $this->assertSame(PaymentStatus::Pending, $card->fresh()->payment_status);

        app(Settings::class)->set('payments.cod_enabled', true);
        auth()->logout();
        $this->addToCart($this->variant());
        $this->post('/checkout', $this->checkoutData($method, ['payment_method' => 'cod']));
        $cod = Order::query()->where('payment_method', 'cod')->sole();

        $this->actingAs($admin)->post(route('admin.orders.cash-collected', $cod))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $cod->fresh()->payment_status);
    }

    public function test_dashboard_reports_sales_and_low_stock(): void
    {
        $this->paidOrder(stock: 3); // leaves 1 in stock → low
        $this->actingAs($this->user('admin'))->get('/admin')->assertOk()
            ->assertSee('$60.00')
            ->assertSee('1 left')
            ->assertSee('Before you go live');
    }

    public function test_customer_list_and_history(): void
    {
        $customer = $this->user();
        Order::factory()->paid()->create(['user_id' => $customer->id, 'email' => $customer->email, 'total' => 4200]);
        $manager = $this->user('manager');

        $this->actingAs($manager)->get('/admin/customers')->assertOk()->assertSee($customer->email)->assertSee('$42.00');
        $this->actingAs($manager)->get(route('admin.customers.show', $customer))->assertOk()->assertSee('$42.00');
    }
}
