<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeCheckout;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for abandoned card checkouts: releases stock held by orders whose
 * payment window passed. Each order is first checked with Stripe so a payment
 * that did go through is never cancelled. Scheduled every five minutes.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel unpaid online orders whose payment window has passed and release their stock';

    public function handle(StripeCheckout $stripe, OrderPayments $payments): int
    {
        $orders = Order::query()
            ->where('payment_method', 'stripe')
            ->where('payment_status', PaymentStatus::Pending)
            ->where('payment_expires_at', '<', now()->subMinutes(2))
            ->limit(100)->get();

        foreach ($orders as $order) {
            try {
                if ($order->stripe_checkout_session_id) {
                    $stripe->refresh($order);
                    if ($order->payment_status === PaymentStatus::Pending) {
                        $stripe->cancel($order, 'Payment window expired without payment.');
                    }
                } else {
                    $payments->markCancelled($order, 'Payment window expired without payment.');
                }
                $this->line("{$order->number}: {$order->fresh()->payment_status->label()}");
            } catch (Throwable $e) {
                report($e);
                $this->warn("{$order->number}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
