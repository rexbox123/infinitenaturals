<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeCheckout;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Receives Stripe webhooks. Every request is verified with the endpoint's signing
 * secret before anything is read from it, and each event is processed at most once.
 */
class StripeWebhookController extends Controller
{
    private const HANDLED = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
    ];

    public function __invoke(Request $request, StripeCheckout $stripe, OrderPayments $payments)
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::error('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');

            return response('Webhook not configured', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
                config('services.stripe.webhook_tolerance', 300),
            )->toArray();
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Rejected Stripe webhook with invalid signature/payload', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        if (! in_array($event['type'], self::HANDLED, true)) {
            return response('Ignored', 200);
        }

        try {
            $record = WebhookEvent::query()->firstOrCreate(
                ['provider' => 'stripe', 'event_id' => $event['id']],
                ['type' => $event['type']],
            );
        } catch (UniqueConstraintViolationException) {
            $record = WebhookEvent::query()->where('provider', 'stripe')->where('event_id', $event['id'])->firstOrFail();
        }

        if ($record->processed_at) {
            return response('Already processed', 200);
        }

        try {
            $this->handle($event, $stripe, $payments);
        } catch (Throwable $e) {
            report($e);

            // Non-2xx makes Stripe retry later.
            return response('Processing failed', 500);
        }

        $record->update(['processed_at' => now()]);

        return response('OK', 200);
    }

    private function handle(array $event, StripeCheckout $stripe, OrderPayments $payments): void
    {
        $object = $event['data']['object'] ?? [];

        if (str_starts_with($event['type'], 'checkout.session.')) {
            $order = $this->findOrder($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? null, $object['id'] ?? null);
            if (! $order) {
                Log::warning('Stripe webhook for unknown order', ['event' => $event['id'], 'session' => $object['id'] ?? null]);

                return;
            }
            $stripe->sync($order, $object, $event['type']);

            return;
        }

        if ($event['type'] === 'charge.refunded') {
            $intent = $object['payment_intent'] ?? null;
            $order = $intent ? Order::query()->where('stripe_payment_intent_id', $intent)->first() : null;
            if ($order && isset($object['amount_refunded'])) {
                $payments->recordRefund($order, (int) $object['amount_refunded'], 'Refund recorded by Stripe.');
            }
        }
    }

    private function findOrder(?string $orderId, ?string $sessionId): ?Order
    {
        if ($orderId && ($order = Order::query()->find($orderId))) {
            return $order;
        }

        return $sessionId ? Order::query()->where('stripe_checkout_session_id', $sessionId)->first() : null;
    }
}
