<?php

namespace App\Services\Payments;

/**
 * The few Stripe API calls the store makes. Wrapped behind an interface so the
 * payment flow can be tested without network access (see tests/Fakes/FakeStripeApi).
 * All methods return Stripe objects as plain arrays.
 */
interface StripeApi
{
    public function createCheckoutSession(array $params, string $idempotencyKey): array;

    public function retrieveCheckoutSession(string $sessionId): array;

    public function expireCheckoutSession(string $sessionId): array;

    public function createRefund(string $paymentIntentId, ?int $amount, string $idempotencyKey): array;

    /** Lightweight authenticated call used by `php artisan store:stripe-check`. */
    public function accountSummary(): array;
}
