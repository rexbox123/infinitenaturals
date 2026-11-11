<?php

namespace App\Services\Payments;

use Stripe\StripeClient;

class StripeSdkApi implements StripeApi
{
    private ?StripeClient $client = null;

    public function __construct(private readonly ?string $secret) {}

    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return $this->client()->checkout->sessions->create($params, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->retrieve($sessionId)->toArray();
    }

    public function expireCheckoutSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->expire($sessionId)->toArray();
    }

    public function createRefund(string $paymentIntentId, ?int $amount, string $idempotencyKey): array
    {
        $params = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        return $this->client()->refunds->create($params, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    public function accountSummary(): array
    {
        $balance = $this->client()->balance->retrieve()->toArray();

        return ['livemode' => $balance['livemode'] ?? null];
    }

    private function client(): StripeClient
    {
        if (! $this->secret) {
            throw new PaymentGatewayException('Stripe is not configured: set STRIPE_SECRET.');
        }

        return $this->client ??= new StripeClient([
            'api_key' => $this->secret,
            'max_network_retries' => 2,
        ]);
    }
}
