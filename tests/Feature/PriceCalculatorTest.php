<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use App\Models\TaxRate;
use App\Services\Checkout\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    public function test_shipping_is_free_at_threshold_and_charged_below(): void
    {
        $calc = app(PriceCalculator::class);
        $method = $this->shipping(595, 5000);

        $below = $calc->calculate(collect([['variant' => $this->variant(3700), 'quantity' => 1]]), null, $method);
        $this->assertSame(595, $below->shipping);
        $this->assertSame(4295, $below->total());

        $at = $calc->calculate(collect([['variant' => $this->variant(2500), 'quantity' => 2]]), null, $method);
        $this->assertSame(0, $at->shipping);
        $this->assertSame(5000, $at->total());
    }

    public function test_discount_is_applied_before_free_shipping_threshold_and_tax(): void
    {
        TaxRate::query()->create(['state' => 'FL', 'rate' => 7, 'applies_to_shipping' => false, 'is_active' => true]);
        $code = DiscountCode::query()->create(['code' => 'TEN', 'type' => DiscountType::Percent, 'value' => 10, 'is_active' => true]);

        $totals = app(PriceCalculator::class)->calculate(
            collect([['variant' => $this->variant(5000), 'quantity' => 1]]), $code, $this->shipping(595, 5000), 'FL',
        );

        $this->assertSame(500, $totals->discount);
        $this->assertSame(595, $totals->shipping, '$45 after discount is below the $50 free-shipping threshold');
        $this->assertSame(315, $totals->tax); // 7% of $45.00
        $this->assertSame(4500 + 595 + 315, $totals->total());
    }

    public function test_tax_on_shipping_when_state_requires_it(): void
    {
        TaxRate::query()->create(['state' => 'NY', 'rate' => 8.875, 'applies_to_shipping' => true, 'is_active' => true]);
        $totals = app(PriceCalculator::class)->calculate(collect([['variant' => $this->variant(1000), 'quantity' => 1]]), null, $this->shipping(500, null), 'NY');

        $this->assertSame((int) round(1500 * 8.875 / 100), $totals->tax);
    }

    public function test_invalid_discount_reports_reason_and_gives_no_discount(): void
    {
        $code = DiscountCode::query()->create(['code' => 'BIG', 'type' => DiscountType::Fixed, 'value' => 1000, 'min_subtotal' => 10000, 'is_active' => true]);
        $totals = app(PriceCalculator::class)->calculate(collect([['variant' => $this->variant(2000), 'quantity' => 1]]), $code);

        $this->assertSame(0, $totals->discount);
        $this->assertStringContainsString('minimum order', $totals->discountMessage);
    }

    public function test_sale_price_only_applies_inside_its_window(): void
    {
        $variant = $this->variant(6900, 5, ['sale_price' => 5900, 'sale_starts_at' => now()->addDay()]);
        $this->assertSame(6900, $variant->currentPrice());

        $variant->update(['sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);
        $this->assertSame(5900, $variant->fresh()->currentPrice());
    }
}
