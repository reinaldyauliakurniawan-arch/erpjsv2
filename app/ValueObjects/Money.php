<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Money value object.
 *
 * Stores amount in minor units (integer) to avoid floating-point rounding errors
 * that plague financial applications. For IDR (Rupiah), 1 minor unit = 1 Rupiah
 * (no decimal subunits in practice), so `Money::fromDecimal('100000.00')` becomes
 * 100000 minor units = Rp 100.000.
 *
 * Design goals:
 *  - Type safety: arithmetic only between Money instances of same currency.
 *  - Immutability: all operations return new instances.
 *  - Explicitness: caller must acknowledge currency mismatch via convert().
 *
 * Usage:
 *   $price = Money::fromDecimal('500000.00');          // Rp 500.000
 *   $paid  = Money::fromMinorUnits(150000);            // Rp 150.000
 *   $sum   = $price->add($paid);                       // Rp 650.000
 *   $zero  = Money::zero();                            // Rp 0
 *
 *   // Comparisons
 *   $price->greaterThan($paid);                        // true
 *   $price->equals(Money::fromDecimal('500000.00'));   // true
 *
 *   // Formatting
 *   $price->format();                                  // "Rp 500.000"
 *   $price->toDecimal();                               // "500000.00"
 */
final class Money implements JsonSerializable, Stringable
{
    public const DEFAULT_CURRENCY = 'IDR';

    public function __construct(
        public readonly int $minorUnits,
        public readonly string $currency = self::DEFAULT_CURRENCY,
    ) {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative. Use subtract() to compute debits/credits explicitly.');
        }
        if ($currency === '' || $currency === '0') {
            throw new InvalidArgumentException('Currency code must be a non-empty ISO 4217 string.');
        }
    }

    // ---------- Factories ----------

    public static function fromMinorUnits(int $minorUnits, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Parse a decimal string like "500000.00" or "1.234.567,89" (Indonesian format).
     * Handles both "." and "," decimal separators safely by stripping thousands separators.
     */
    public static function fromDecimal(string|float|int $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        if (is_int($amount)) {
            return new self($amount, $currency);
        }

        if (is_float($amount)) {
            // Float is risky; round to nearest minor unit
            return new self((int) round($amount), $currency);
        }

        // String: strip everything except digits, sign, and decimal separator
        $normalized = trim($amount);
        if ($normalized === '') {
            throw new InvalidArgumentException('Cannot create Money from empty string.');
        }

        // Indonesian format: 1.234.567,89 → 1234567.89
        // Western format:   1,234,567.89 → 1234567.89
        $hasComma = str_contains($normalized, ',');
        $hasDot   = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            // Heuristic: the last separator is the decimal one
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                // Indonesian: dots are thousands, comma is decimal
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                // Western: commas are thousands, dot is decimal
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            // Only comma — treat as decimal separator
            $normalized = str_replace(',', '.', $normalized);
        }
        // Strip any remaining non-numeric chars except dot and minus
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            throw new InvalidArgumentException("Cannot parse '{$amount}' as Money.");
        }

        $float = (float) $normalized;
        // For IDR, no sub-minor units — round to integer
        return new self((int) round($float), $currency);
    }

    public static function zero(string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self(0, $currency);
    }

    // ---------- Arithmetic ----------

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function multiply(int|float $factor): self
    {
        if (is_float($factor)) {
            return new self((int) round($this->minorUnits * $factor), $this->currency);
        }
        return new self($this->minorUnits * $factor, $this->currency);
    }

    public function divide(int|float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide Money by zero.');
        }
        return new self((int) round($this->minorUnits / $divisor), $this->currency);
    }

    // ---------- Comparisons ----------

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits > $other->minorUnits;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits >= $other->minorUnits;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits < $other->minorUnits;
    }

    public function lessThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits <= $other->minorUnits;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    // ---------- Conversions ----------

    public function toMinorUnits(): int
    {
        return $this->minorUnits;
    }

    public function toDecimal(): string
    {
        return number_format((float) $this->minorUnits, 2, '.', '');
    }

    public function format(): string
    {
        // Indonesian Rupiah formatting: "Rp 1.234.567"
        $formatted = number_format($this->minorUnits, 0, ',', '.');
        return $this->currency === 'IDR' ? "Rp {$formatted}" : "{$this->currency} {$formatted}";
    }

    public function jsonSerialize(): array
    {
        return [
            'amount'   => $this->minorUnits,
            'currency' => $this->currency,
            'display'  => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    // ---------- Internal ----------

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}. Use convert() explicitly."
            );
        }
    }
}
