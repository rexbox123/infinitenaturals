<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_cents(): void
    {
        $this->assertSame('$69.00', Money::format(6900));
        $this->assertSame('$1,234.50', Money::format(123450));
        $this->assertSame('-$5.95', Money::format(-595));
    }

    public function test_parses_user_input_without_float_errors(): void
    {
        $this->assertSame(6900, Money::toCents('69'));
        $this->assertSame(850, Money::toCents('8.5'));
        $this->assertSame(1649, Money::toCents('16.49'));
        $this->assertSame(106900, Money::toCents('$1,069.00'));
        $this->assertNull(Money::toCents(''));
    }

    public function test_rejects_invalid_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12.345');
    }
}
