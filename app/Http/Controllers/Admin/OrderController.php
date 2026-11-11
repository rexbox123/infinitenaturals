<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\StripeCheckout;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()->withCount('items')
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('last_name', 'like', $like)->orWhere('first_name', 'like', $like)
                    ->orWhere('tracking_number', 'like', $like));
            })
            ->when($request->query('payment'), fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->query('fulfillment'), fn ($q, $s) => $q->where('fulfillment_status', $s))
            ->when($request->query('view') === 'to_fulfil', fn ($q) => $q
                ->where(fn ($w) => $w->where('payment_status', PaymentStatus::Paid)
                    ->orWhere(fn ($c) => $c->where('payment_method', 'cod')->where('payment_status', PaymentStatus::Pending)))
                ->whereIn('fulfillment_status', [FulfillmentStatus::Unfulfilled, FulfillmentStatus::Processing]))
            ->when($request->query('view') === 'review', fn ($q) => $q->where('needs_review', true))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['items.variant.product.primaryImage', 'events.user', 'user', 'stockMovements']);

        return view('admin.orders.show', [
            'order' => $order,
            'carriers' => config('store.tracking_carriers'),
            'stripeConfigured' => StripeCheckout::isConfigured(),
        ]);
    }

    public function packingSlip(Order $order)
    {
        return view('admin.orders.packing-slip', ['order' => $order->load('items')]);
    }

    public function updateFulfillment(Request $request, Order $order)
    {
        $data = $request->validate([
            'fulfillment_status' => ['required', Rule::in(['unfulfilled', 'processing', 'shipped', 'delivered'])],
            'tracking_carrier' => ['nullable', Rule::in(array_keys(config('store.tracking_carriers')))],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url:https,http', 'max:500'],
            'notify_customer' => ['boolean'],
        ]);

        if ($order->fulfillment_status === FulfillmentStatus::Cancelled) {
            return back()->withErrors(['fulfillment_status' => 'This order is cancelled.']);
        }

        $status = FulfillmentStatus::from($data['fulfillment_status']);
        $canFulfil = $order->payment_status === PaymentStatus::Paid
            || ($order->isCashOnDelivery() && $order->payment_status === PaymentStatus::Pending);
        if ($status !== FulfillmentStatus::Unfulfilled && ! $canFulfil) {
            return back()->withErrors(['fulfillment_status' => 'Only paid orders (or cash-on-delivery orders) can be fulfilled.']);
        }

        $previous = $order->fulfillment_status;
        $order->fill([
            'fulfillment_status' => $status,
            'tracking_carrier' => $data['tracking_carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'tracking_url' => $data['tracking_url'] ?? null,
        ]);
        if ($status === FulfillmentStatus::Shipped && ! $order->shipped_at) {
            $order->shipped_at = now();
        }
        if ($status === FulfillmentStatus::Delivered && ! $order->delivered_at) {
            $order->delivered_at = now();
            $order->shipped_at ??= now();
        }
        $order->save();

        if ($previous !== $status) {
            $message = "Status changed to {$status->label()}.";
            if ($status === FulfillmentStatus::Shipped && $order->tracking_number) {
                $message .= " Tracking: {$order->carrierName()} {$order->tracking_number}.";
            }
            $order->addEvent('status', $message, true, $request->user());
        } elseif ($order->wasChanged(['tracking_number', 'tracking_carrier', 'tracking_url'])) {
            $order->addEvent('status', 'Tracking details updated.', true, $request->user());
        }

        if ($status === FulfillmentStatus::Shipped && $request->boolean('notify_customer')
            && ($previous !== $status || $order->wasChanged('tracking_number'))) {
            Mail::to($order->email)->send(new OrderShipped($order));
            $order->addEvent('email', 'Shipping confirmation emailed to customer.', false, $request->user());
        }

        return back()->with('status', 'Order updated.');
    }

    public function addNote(Request $request, Order $order)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'is_customer_visible' => ['boolean'],
        ]);
        $order->addEvent('note', $data['message'], $request->boolean('is_customer_visible'), $request->user());

        return back()->with('status', 'Note added.');
    }

    public function markCashCollected(Request $request, Order $order, OrderPayments $payments)
    {
        try {
            $payments->markCashCollected($order, $request->user());
        } catch (PaymentGatewayException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment recorded.');
    }

    public function syncPayment(Order $order, StripeCheckout $stripe)
    {
        try {
            $stripe->refresh($order);
        } catch (Throwable $e) {
            return back()->withErrors(['payment' => 'Could not reach Stripe: '.$e->getMessage()]);
        }

        return back()->with('status', 'Payment status checked with Stripe: '.$order->payment_status->label().'.');
    }

    public function cancel(Request $request, Order $order, OrderPayments $payments, StripeCheckout $stripe)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'restock' => ['boolean'],
        ]);

        if (! $order->isCancellable()) {
            return back()->withErrors(['cancel' => 'Shipped or delivered orders cannot be cancelled. Record a refund instead.']);
        }
        if (in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true) && ! $order->isCashOnDelivery()) {
            return back()->withErrors(['cancel' => 'This order has been paid. Refund it first (Refund panel), then cancel.']);
        }

        // Make sure an open Stripe page can no longer be paid.
        if ($order->payment_status === PaymentStatus::Pending && $order->stripe_checkout_session_id) {
            $stripe->cancel($order, 'Cancelled by staff'.(($data['reason'] ?? '') ? ': '.$data['reason'] : '.'));
            $order->refresh();
        }

        $payments->cancelByStaff($order, $request->user(), $data['reason'] ?? '', $request->boolean('restock', true));

        return back()->with('status', 'Order cancelled.');
    }

    public function refund(Request $request, Order $order, StripeCheckout $stripe, OrderPayments $payments, InventoryService $inventory)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'restock' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $refundable = $order->total - $order->amount_refunded;
        $amount = isset($data['amount']) ? Money::toCents($data['amount']) : $refundable;
        if ($amount <= 0 || $amount > $refundable) {
            return back()->withErrors(['amount' => 'Refund amount must be between $0.01 and '.money($refundable).'.']);
        }
        if (! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            return back()->withErrors(['amount' => 'Only paid orders can be refunded.']);
        }

        try {
            if ($order->isCashOnDelivery()) {
                $payments->recordRefund($order, $order->amount_refunded + $amount, 'Refund recorded (paid outside Stripe).', $request->user());
            } else {
                $stripe->refund($order, $amount);
            }
        } catch (Throwable $e) {
            return back()->withErrors(['amount' => 'Refund failed: '.$e->getMessage()]);
        }

        if ($data['reason'] ?? null) {
            $order->addEvent('note', 'Refund reason: '.$data['reason'], false, $request->user());
        }
        if ($request->boolean('restock')) {
            $inventory->releaseForOrder($order, $request->user(), 'Refunded');
        }

        return back()->with('status', money($amount).' refunded.');
    }

    public function resendConfirmation(Request $request, Order $order)
    {
        Mail::to($order->email)->send(new OrderConfirmation($order));
        $order->addEvent('email', 'Order confirmation re-sent.', false, $request->user());

        return back()->with('status', 'Confirmation email sent.');
    }

    public function clearReview(Request $request, Order $order)
    {
        $order->forceFill(['needs_review' => false])->save();
        $order->addEvent('note', 'Marked as reviewed.', false, $request->user());

        return back()->with('status', 'Review flag cleared.');
    }
}
