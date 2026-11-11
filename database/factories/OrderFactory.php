<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'IN-'.fake()->unique()->numberBetween(200000, 999999),
            'access_token' => Str::random(48),
            'email' => fake()->safeEmail(),
            'phone' => '352-555-0100',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'address_line1' => fake()->streetAddress(),
            'city' => 'Ocala',
            'state' => 'FL',
            'postal_code' => '34480',
            'shipping_method_name' => 'USPS Standard',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'stripe',
            'payment_status' => PaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['payment_status' => PaymentStatus::Paid, 'paid_at' => now(), 'stripe_payment_intent_id' => 'pi_test_'.Str::random(8)]);
    }
}
