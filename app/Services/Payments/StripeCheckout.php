<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Stripe\Exception\ApiErrorException;
use Throwable;

/**
 * Stripe Checkout (hosted payment page). Card data is entered on Stripe's page
 * and never touches this server. Orders are only marked paid after the server
 * has confirmed the payment with Stripe (signed webhook or API lookup).
 */
class StripeCheckout
{
    public function __construct(
        private readonly StripeApi $api,
        private readonly OrderPayments $payments,
    ) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public static function isAvailable(): bool
    {
        return self::isConfigured() && (bool) setting('payments.stripe_enabled', true);
    }

    /** "test", "live" or null when no key is set. */
    public static function mode(): ?string
    {
        $secret = (string) config('services.stripe.secret');

        return match (true) {
            $secret === '' => null,
            str_contains($secret, '_live_') => 'live',
            default => 'test',
        };
    }

    /** Create a Checkout Session for the order and return the URL to redirect the customer to. */
    public function start(Order $order): string
    {
        $order->loadMissing('items');

        $description = $order->items
            ->map(fn ($i) => "{$i->quantity} × {$i->displayName()}")
            ->implode(', ');

        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->total,
                    'product_data' => [
                        'name' => setting('store.name')." order {$order->number}",
                        'description' => mb_strimwidth($description, 0, 480, '…'),
                    ],
                ],
            ]],
            'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->number],
            'payment_intent_data' => [
                'description' => "Order {$order->number}",
                'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->number],
            ],
            'success_url' => URL::signedRoute('checkout.stripe.return', ['order' => $order->number]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => URL::signedRoute('checkout.stripe.cancel', ['order' => $order->number]),
            'expires_at' => ($order->payment_expires_at ?? now()->addMinutes(31))->getTimestamp(),
        ];

        try {
            $session = $this->api->createCheckoutSession($params, "order-{$order->id}-checkout");
        } catch (ApiErrorException|PaymentGatewayException $e) {
            Log::error('Stripe checkout session creation failed', ['order' => $order->number, 'error' => $e->getMessage()]);
            throw new PaymentGatewayException('We could not reach our payment provider. Please try again in a moment.', previous: $e);
        }

        $order->forceFill(['stripe_checkout_session_id' => $session['id']])->save();
        $order->addEvent('payment', 'Customer redirected to Stripe Checkout.');

        return $session['url'];
    }

    /** URL of the still-open Checkout page so a customer can resume paying, or null. */
    public function resumeUrl(Order $order): ?string
    {
        if (! $order->stripe_checkout_session_id || $order->payment_status !== PaymentStatus::Pending) {
            return null;
        }

        try {
            $session = $this->api->retrieveCheckoutSession($order->stripe_checkout_session_id);
        } catch (Throwable $e) {
            Log::warning('Stripe session lookup failed', ['order' => $order->number, 'error' => $e->getMessage()]);

            return null;
        }

        if (($session['status'] ?? null) === 'open') {
            return $session['url'] ?? null;
        }

        $this->sync($order, $session);

        return null;
    }

    /** Ask Stripe for the session's current state and apply it to the order. */
    public function refresh(Order $order): Order
    {
        if ($order->stripe_checkout_session_id) {
            $this->sync($order, $this->api->retrieveCheckoutSession($order->stripe_checkout_session_id));
        }

        return $order->refresh();
    }

    /**
     * Apply a Checkout Session object (from the API or a verified webhook) to the order.
     * Safe to call repeatedly; OrderPayments ignores transitions that no longer apply.
     */
    public function sync(Order $order, array $session, ?string $eventType = null): void
    {
        if (! $this->belongsTo($order, $session)) {
            $order->forceFill(['needs_review' => true])->save();
            $order->addEvent('payment', 'Ignored a Stripe session that does not match this order ('.($session['id'] ?? '?').').');
            Log::warning('Stripe session/order mismatch', ['order' => $order->number, 'session' => $session['id'] ?? null]);

            return;
        }

        $paymentIntent = is_array($session['payment_intent'] ?? null)
            ? ($session['payment_intent']['id'] ?? null)
            : ($session['payment_intent'] ?? null);

        if ($eventType === 'checkout.session.async_payment_failed') {
            $this->payments->markFailed($order, 'The payment could not be completed.', $paymentIntent);

            return;
        }

        $status = $session['status'] ?? null;
        $paymentStatus = $session['payment_status'] ?? null;

        if ($status === 'complete' && in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            if ((int) ($session['amount_total'] ?? -1) !== $order->total
                || strtolower((string) ($session['currency'] ?? '')) !== strtolower($order->currency)) {
                $order->forceFill(['needs_review' => true])->save();
                $order->addEvent('payment', sprintf(
                    'Stripe reported %s %s paid but the order total is %s. Not marked as paid — please review.',
                    strtoupper((string) ($session['currency'] ?? '')),
                    money((int) ($session['amount_total'] ?? 0)),
                    money($order->total),
                ));

                return;
            }

            $this->payments->markPaid($order, $paymentIntent, 'Stripe');

            return;
        }

        if ($status === 'complete' && $paymentStatus === 'unpaid') {
            $this->payments->markProcessing($order, $paymentIntent);

            return;
        }

        if ($status === 'expired') {
            $this->payments->markCancelled($order, 'Checkout session expired without payment.');
        }
    }

    /**
     * Customer abandoned Stripe Checkout: expire the session so it can no longer be paid,
     * then cancel the order and release its stock. Returns false when it was paid after all.
     */
    public function cancel(Order $order, string $reason = 'Customer cancelled payment.'): bool
    {
        if ($order->payment_status !== PaymentStatus::Pending) {
            return false;
        }

        if ($order->stripe_checkout_session_id) {
            try {
                $session = $this->api->expireCheckoutSession($order->stripe_checkout_session_id);
                if (($session['status'] ?? null) !== 'expired') {
                    $this->sync($order, $session);
                }
            } catch (Throwable $e) {
                // Already complete or expired — look at its real state instead.
                $this->refresh($order);
                if ($order->payment_status !== PaymentStatus::Pending) {
                    return $order->payment_status === PaymentStatus::Cancelled;
                }
                Log::warning('Could not expire Stripe session', ['order' => $order->number, 'error' => $e->getMessage()]);

                return false;
            }
        }

        $this->payments->markCancelled($order->refresh(), $reason);

        return $order->refresh()->payment_status === PaymentStatus::Cancelled;
    }

    public function refund(Order $order, ?int $amount = null): array
    {
        if (! $order->stripe_payment_intent_id) {
            throw new PaymentGatewayException('This order has no Stripe payment to refund.');
        }

        $amount ??= $order->total - $order->amount_refunded;
        $refund = $this->api->createRefund(
            $order->stripe_payment_intent_id,
            $amount,
            "order-{$order->id}-refund-{$order->amount_refunded}-{$amount}",
        );

        if (in_array($refund['status'] ?? null, ['succeeded', 'pending'], true)) {
            $this->payments->recordRefund($order, $order->amount_refunded + $amount, 'Refund issued via Stripe ('.($refund['id'] ?? '').').');
        } else {
            throw new PaymentGatewayException('Stripe did not accept the refund (status: '.($refund['status'] ?? 'unknown').').');
        }

        return $refund;
    }

    private function belongsTo(Order $order, array $session): bool
    {
        $metaOrder = $session['metadata']['order_id'] ?? $session['client_reference_id'] ?? null;

        return (string) $metaOrder === (string) $order->id
            && ($order->stripe_checkout_session_id === null || $order->stripe_checkout_session_id === ($session['id'] ?? null));
    }
}
