<?php

namespace App\Services\Payments;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderNotification;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The order payment state machine. Every method locks the order row and only
 * applies a transition that is valid from the current state, so webhooks that
 * arrive twice or out of order (and the customer's return redirect racing the
 * webhook) cannot double-process an order.
 */
class OrderPayments
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function markPaid(Order $order, ?string $paymentIntent, string $source, ?User $by = null): bool
    {
        $changed = $this->transition($order, function (Order $o) use ($paymentIntent, $source, $by) {
            if (in_array($o->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
                return false;
            }

            $wasVoided = $o->stock_released_at !== null;
            $o->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'stripe_payment_intent_id' => $paymentIntent ?? $o->stripe_payment_intent_id,
                'needs_review' => $o->needs_review || $wasVoided,
            ])->save();

            $o->addEvent('payment', "Payment confirmed ({$source}).", true, $by);
            if ($wasVoided) {
                $o->addEvent('payment', 'Payment arrived after this order was cancelled and its stock released. Check stock, then fulfil or refund.');
            }

            return true;
        });

        if ($changed) {
            $this->sendConfirmation($order);
        }

        return $changed;
    }

    /** Checkout completed with a delayed payment method; funds not confirmed yet. */
    public function markProcessing(Order $order, ?string $paymentIntent): bool
    {
        $changed = $this->transition($order, function (Order $o) use ($paymentIntent) {
            if ($o->payment_status !== PaymentStatus::Pending) {
                return false;
            }
            $o->forceFill([
                'payment_status' => PaymentStatus::Processing,
                'stripe_payment_intent_id' => $paymentIntent ?? $o->stripe_payment_intent_id,
            ])->save();
            $o->addEvent('payment', 'Payment submitted and is being processed by the bank. We will ship once it clears.', true);

            return true;
        });

        if ($changed) {
            $this->sendConfirmation($order);
        }

        return $changed;
    }

    public function markFailed(Order $order, string $reason, ?string $paymentIntent = null): bool
    {
        return $this->void($order, PaymentStatus::Failed, $reason, $paymentIntent);
    }

    public function markCancelled(Order $order, string $reason, ?User $by = null): bool
    {
        return $this->void($order, PaymentStatus::Cancelled, $reason, null, $by);
    }

    /** Manual payment confirmation from the admin (cash on delivery only). */
    public function markCashCollected(Order $order, User $by): bool
    {
        if (! $order->isCashOnDelivery()) {
            throw new PaymentGatewayException('Online payments are confirmed by Stripe and cannot be marked paid manually.');
        }

        return $this->markPaid($order, null, 'cash collected', $by);
    }

    public function recordRefund(Order $order, int $totalRefunded, string $message, ?User $by = null): bool
    {
        return $this->transition($order, function (Order $o) use ($totalRefunded, $message, $by) {
            if ($totalRefunded <= $o->amount_refunded) {
                return false;
            }
            $o->forceFill([
                'amount_refunded' => min($totalRefunded, $o->total),
                'payment_status' => $totalRefunded >= $o->total ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();
            $o->addEvent('payment', $message.' Total refunded: '.money($o->amount_refunded).'.', true, $by);

            return true;
        });
    }

    /** Cancel an unpaid order in admin, or after a refund. Releases stock. */
    public function cancelByStaff(Order $order, User $by, string $reason, bool $restock = true): void
    {
        DB::transaction(function () use ($order, $by, $reason, $restock) {
            $o = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($o->fulfillment_status === FulfillmentStatus::Cancelled) {
                return;
            }

            $updates = ['fulfillment_status' => FulfillmentStatus::Cancelled, 'cancelled_at' => now()];
            if (in_array($o->payment_status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                $updates['payment_status'] = PaymentStatus::Cancelled;
            }
            $o->forceFill($updates)->save();
            $o->addEvent('status', 'Order cancelled'.($reason ? ": {$reason}" : '.'), true, $by);

            if ($restock) {
                $this->inventory->releaseForOrder($o, $by, 'Cancelled by staff');
            }
            $this->releaseDiscount($o);
        });
        $order->refresh();
    }

    private function void(Order $order, PaymentStatus $status, string $reason, ?string $paymentIntent = null, ?User $by = null): bool
    {
        $changed = $this->transition($order, function (Order $o) use ($status, $reason, $paymentIntent, $by) {
            if (! in_array($o->payment_status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                return false;
            }
            $o->forceFill([
                'payment_status' => $status,
                'fulfillment_status' => FulfillmentStatus::Cancelled,
                'cancelled_at' => now(),
                'stripe_payment_intent_id' => $paymentIntent ?? $o->stripe_payment_intent_id,
            ])->save();
            $o->addEvent('payment', $reason, true, $by);
            $this->releaseDiscount($o);

            return true;
        });

        if ($changed) {
            $this->inventory->releaseForOrder($order, $by, $status === PaymentStatus::Failed ? 'Payment failed' : 'Unpaid order cancelled');
        }

        return $changed;
    }

    private function releaseDiscount(Order $o): void
    {
        if ($o->discount_code_id) {
            DB::table('discount_codes')->where('id', $o->discount_code_id)->where('times_used', '>', 0)->decrement('times_used');
        }
    }

    /** Run $change on a locked, fresh copy of the order; refresh the caller's instance afterwards. */
    private function transition(Order $order, callable $change): bool
    {
        $changed = DB::transaction(function () use ($order, $change) {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            return (bool) $change($fresh);
        });

        $order->refresh();

        return $changed;
    }

    public function sendConfirmation(Order $order): void
    {
        $claimed = Order::query()->whereKey($order->id)->whereNull('confirmation_sent_at')
            ->update(['confirmation_sent_at' => now()]);

        if (! $claimed) {
            return;
        }

        $order->refresh();
        Mail::to($order->email)->send(new OrderConfirmation($order));
        if ($notify = setting('store.order_notification_email')) {
            Mail::to($notify)->send(new NewOrderNotification($order));
        }
        $order->addEvent('email', 'Order confirmation emailed to customer.');
    }
}
