<?php

namespace App\Console\Commands;

use App\Services\Payments\StripeApi;
use App\Services\Payments\StripeCheckout;
use Illuminate\Console\Command;
use Throwable;

class StripeCheck extends Command
{
    protected $signature = 'store:stripe-check';

    protected $description = 'Verify the Stripe keys in the environment and show which mode (test/live) is active';

    public function handle(StripeApi $api): int
    {
        if (! StripeCheckout::isConfigured()) {
            $this->error('STRIPE_SECRET is not set.');

            return self::FAILURE;
        }

        try {
            $summary = $api->accountSummary();
        } catch (Throwable $e) {
            $this->error('Stripe rejected the key: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Stripe key OK — '.(($summary['livemode'] ?? false) ? 'LIVE mode (real charges)' : 'TEST mode (no real charges)').'.');
        $this->line('Webhook signing secret: '.(config('services.stripe.webhook_secret') ? 'set' : 'MISSING (set STRIPE_WEBHOOK_SECRET)'));
        $this->line('Webhook endpoint to register in Stripe: '.route('webhooks.stripe'));

        return self::SUCCESS;
    }
}
