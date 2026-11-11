<?php

namespace Tests\Fakes;

use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\StripeApi;
use Illuminate\Support\Str;

/**
 * In-memory stand-in for Stripe used by the test suite. It models the
 * Checkout Session lifecycle (open → complete/expired) closely enough to
 * exercise every branch of the payment flow without network access.
 */
class FakeStripeApi implements StripeApi
{
    /** @var array<string, array> */
    public array $sessions = [];

    public array $refunds = [];

    public array $created = [];

    public bool $failCreate = false;

    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        if ($this->failCreate) {
            throw new PaymentGatewayException('Stripe unavailable');
        }

        $id = 'cs_test_'.Str::random(12);
        $this->created[] = $params;

        return $this->sessions[$id] = [
            'id' => $id,
            'object' => 'checkout.session',
            'url' => "https://checkout.stripe.test/c/pay/{$id}",
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => $params['line_items'][0]['price_data']['unit_amount'],
            'currency' => $params['line_items'][0]['price_data']['currency'],
            'client_reference_id' => $params['client_reference_id'],
            'metadata' => $params['metadata'],
            'payment_intent' => null,
        ];
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? throw new PaymentGatewayException("No such session {$sessionId}");
    }

    public function expireCheckoutSession(string $sessionId): array
    {
        $session = $this->retrieveCheckoutSession($sessionId);
        if ($session['status'] !== 'open') {
            throw new PaymentGatewayException('Only open sessions can be expired.');
        }

        return $this->sessions[$sessionId] = ['status' => 'expired'] + $session;
    }

    public function createRefund(string $paymentIntentId, ?int $amount, string $idempotencyKey): array
    {
        return $this->refunds[] = ['id' => 're_test_'.Str::random(8), 'payment_intent' => $paymentIntentId, 'amount' => $amount, 'status' => 'succeeded'];
    }

    public function accountSummary(): array
    {
        return ['livemode' => false];
    }

    /** Simulate the customer finishing checkout on Stripe. */
    public function complete(string $sessionId, bool $paid = true, ?int $amount = null): array
    {
        $session = $this->retrieveCheckoutSession($sessionId);

        return $this->sessions[$sessionId] = array_merge($session, [
            'status' => 'complete',
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'payment_intent' => 'pi_test_'.Str::random(10),
            'amount_total' => $amount ?? $session['amount_total'],
        ]);
    }

    public function latestSessionId(): string
    {
        return array_key_last($this->sessions);
    }
}
