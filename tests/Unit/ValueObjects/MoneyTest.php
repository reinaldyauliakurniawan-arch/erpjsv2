<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    // =========================================================
    //  FACTORY: fromMinorUnits
    // =========================================================

    #[Test]
    public function from_minor_units_creates_money_with_correct_amount(): void
    {
        $money = Money::fromMinorUnits(500_000);

        $this->assertSame(500_000, $money->minorUnits);
        $this->assertSame('IDR', $money->currency);
    }

    #[Test]
    public function from_minor_units_accepts_custom_currency(): void
    {
        $money = Money::fromMinorUnits(100, 'USD');

        $this->assertSame(100, $money->minorUnits);
        $this->assertSame('USD', $money->currency);
    }

    // =========================================================
    //  FACTORY: fromDecimal
    // =========================================================

    #[Test]
    public function from_decimal_parses_integer_string(): void
    {
        $money = Money::fromDecimal('500000');

        $this->assertSame(500_000, $money->minorUnits);
    }

    #[Test]
    public function from_decimal_parses_decimal_string_with_dot(): void
    {
        $money = Money::fromDecimal('500000.00');

        $this->assertSame(500_000, $money->minorUnits);
    }

    #[Test]
    public function from_decimal_parses_indonesian_format_with_thousands_separator(): void
    {
        $money = Money::fromDecimal('1.234.567');

        $this->assertSame(1_234_567, $money->minorUnits);
    }

    #[Test]
    public function from_decimal_parses_indonesian_decimal_format(): void
    {
        $money = Money::fromDecimal('1.234.567,89');

        $this->assertSame(1_234_568, $money->minorUnits); // rounded to minor unit
    }

    #[Test]
    public function from_decimal_parses_western_format(): void
    {
        $money = Money::fromDecimal('1,234,567.89');

        $this->assertSame(1_234_568, $money->minorUnits);
    }

    #[Test]
    public function from_decimal_accepts_int(): void
    {
        $money = Money::fromDecimal(750_000);

        $this->assertSame(750_000, $money->minorUnits);
    }

    #[Test]
    public function from_decimal_accepts_float(): void
    {
        $money = Money::fromDecimal(99_999.99);

        $this->assertSame(100_000, $money->minorUnits); // rounded
    }

    #[Test]
    public function from_decimal_throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('');
    }

    #[Test]
    public function from_decimal_throws_on_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('not-a-number');
    }

    // =========================================================
    //  FACTORY: zero
    // =========================================================

    #[Test]
    public function zero_creates_zero_value_money(): void
    {
        $money = Money::zero();

        $this->assertTrue($money->isZero());
        $this->assertSame(0, $money->minorUnits);
    }

    // =========================================================
    //  CONSTRUCTOR VALIDATION
    // =========================================================

    #[Test]
    public function constructor_rejects_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-100);
    }

    #[Test]
    public function constructor_rejects_empty_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(100, '');
    }

    // =========================================================
    //  ARITHMETIC
    // =========================================================

    #[Test]
    public function add_returns_new_money_with_sum(): void
    {
        $a = Money::fromMinorUnits(100);
        $b = Money::fromMinorUnits(200);

        $sum = $a->add($b);

        $this->assertSame(300, $sum->minorUnits);
        // Original objects must be unchanged (immutability)
        $this->assertSame(100, $a->minorUnits);
        $this->assertSame(200, $b->minorUnits);
    }

    #[Test]
    public function subtract_returns_new_money_with_difference(): void
    {
        $a = Money::fromMinorUnits(500);
        $b = Money::fromMinorUnits(200);

        $diff = $a->subtract($b);

        $this->assertSame(300, $diff->minorUnits);
    }

    #[Test]
    public function subtract_can_produce_negative_result(): void
    {
        // Unlike constructor (which rejects negative), subtract may produce
        // negative intermediate results — needed for balance calculations.
        $a = Money::fromMinorUnits(100);
        $b = Money::fromMinorUnits(300);

        $diff = $a->subtract($b);

        $this->assertTrue($diff->isNegative());
        $this->assertSame(-200, $diff->minorUnits);
    }

    #[Test]
    public function multiply_by_int_scales_amount(): void
    {
        $money = Money::fromMinorUnits(100);

        $this->assertSame(500, $money->multiply(5)->minorUnits);
    }

    #[Test]
    public function multiply_by_float_rounds_correctly(): void
    {
        $money = Money::fromMinorUnits(99);

        // 99 * 1.5 = 148.5 → rounds to 149
        $this->assertSame(149, $money->multiply(1.5)->minorUnits);
    }

    #[Test]
    public function divide_by_int_returns_floor_or_round(): void
    {
        $money = Money::fromMinorUnits(1000);

        $this->assertSame(333, $money->divide(3)->minorUnits); // 333.33 → 333
    }

    #[Test]
    public function divide_by_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinorUnits(100)->divide(0);
    }

    #[Test]
    public function add_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinorUnits(100, 'IDR')->add(Money::fromMinorUnits(100, 'USD'));
    }

    // =========================================================
    //  COMPARISONS
    // =========================================================

    #[Test]
    public function equals_returns_true_for_same_amount_and_currency(): void
    {
        $a = Money::fromDecimal('500000');
        $b = Money::fromDecimal('500000.00');

        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function equals_returns_false_for_different_amount(): void
    {
        $this->assertFalse(
            Money::fromMinorUnits(100)->equals(Money::fromMinorUnits(101))
        );
    }

    #[Test]
    public function equals_returns_false_for_different_currency(): void
    {
        $this->assertFalse(
            Money::fromMinorUnits(100, 'IDR')->equals(Money::fromMinorUnits(100, 'USD'))
        );
    }

    #[Test]
    public function greater_than_compares_amounts(): void
    {
        $this->assertTrue(Money::fromMinorUnits(200)->greaterThan(Money::fromMinorUnits(100)));
        $this->assertFalse(Money::fromMinorUnits(100)->greaterThan(Money::fromMinorUnits(200)));
        $this->assertFalse(Money::fromMinorUnits(100)->greaterThan(Money::fromMinorUnits(100)));
    }

    #[Test]
    public function greater_than_or_equal_handles_equality(): void
    {
        $this->assertTrue(Money::fromMinorUnits(100)->greaterThanOrEqual(Money::fromMinorUnits(100)));
        $this->assertTrue(Money::fromMinorUnits(101)->greaterThanOrEqual(Money::fromMinorUnits(100)));
        $this->assertFalse(Money::fromMinorUnits(99)->greaterThanOrEqual(Money::fromMinorUnits(100)));
    }

    #[Test]
    public function less_than_compares_amounts(): void
    {
        $this->assertTrue(Money::fromMinorUnits(100)->lessThan(Money::fromMinorUnits(200)));
        $this->assertFalse(Money::fromMinorUnits(200)->lessThan(Money::fromMinorUnits(100)));
    }

    #[Test]
    public function is_zero_is_correct(): void
    {
        $this->assertTrue(Money::zero()->isZero());
        $this->assertFalse(Money::fromMinorUnits(1)->isZero());
    }

    #[Test]
    public function is_positive_is_correct(): void
    {
        $this->assertTrue(Money::fromMinorUnits(1)->isPositive());
        $this->assertFalse(Money::zero()->isPositive());
    }

    #[Test]
    public function is_negative_is_correct(): void
    {
        $this->assertTrue(Money::fromMinorUnits(100)->subtract(Money::fromMinorUnits(200))->isNegative());
        $this->assertFalse(Money::fromMinorUnits(100)->isNegative());
    }

    // =========================================================
    //  CONVERSIONS
    // =========================================================

    #[Test]
    public function to_minor_units_returns_int(): void
    {
        $this->assertSame(500_000, Money::fromMinorUnits(500_000)->toMinorUnits());
    }

    #[Test]
    public function to_decimal_returns_string_with_two_decimals(): void
    {
        $this->assertSame('500000.00', Money::fromMinorUnits(500_000)->toDecimal());
    }

    #[Test]
    public function format_returns_indonesian_rupiah_format(): void
    {
        $this->assertSame('Rp 1.234.567', Money::fromMinorUnits(1_234_567)->format());
    }

    #[Test]
    public function to_string_returns_formatted(): void
    {
        $money = Money::fromMinorUnits(500_000);
        $this->assertSame('Rp 500.000', (string) $money);
    }

    #[Test]
    public function json_serialize_returns_array_with_amount_currency_display(): void
    {
        $json = Money::fromMinorUnits(500_000)->jsonSerialize();

        $this->assertSame(500_000, $json['amount']);
        $this->assertSame('IDR', $json['currency']);
        $this->assertSame('Rp 500.000', $json['display']);
    }

    // =========================================================
    //  REAL-WORLD USE CASE: Double-entry balance check
    // =========================================================

    #[Test]
    public function balance_check_sums_debits_and_credits_correctly(): void
    {
        // Simulate journal items: debit 100k + 50k, credit 150k → balanced
        $debits  = Money::fromMinorUnits(100_000)->add(Money::fromMinorUnits(50_000));
        $credits = Money::fromMinorUnits(150_000);

        $this->assertTrue($debits->equals($credits));
    }

    #[Test]
    public function balance_check_detects_mismatch(): void
    {
        $debits  = Money::fromMinorUnits(100_000);
        $credits = Money::fromMinorUnits(99_999);

        $this->assertFalse($debits->equals($credits));
        $this->assertTrue($debits->subtract($credits)->isPositive());
    }
}
