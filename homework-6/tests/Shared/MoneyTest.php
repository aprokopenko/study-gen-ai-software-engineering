<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Shared;

use BankingPipeline\Shared\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Money::parse()
    // -------------------------------------------------------------------------

    #[Test]
    public function parseAcceptsPositiveDecimalString(): void
    {
        $this->assertSame('1500.00', Money::parse('1500.00'));
    }

    #[Test]
    public function parseAcceptsZero(): void
    {
        $this->assertSame('0', Money::parse('0'));
    }

    #[Test]
    public function parseAcceptsNegativeAmount(): void
    {
        // Parse does NOT enforce positivity — that is the Validator's job
        $this->assertSame('-100.00', Money::parse('-100.00'));
    }

    #[Test]
    public function parseAcceptsIntegerString(): void
    {
        $this->assertSame('1500', Money::parse('1500'));
    }

    #[Test]
    #[DataProvider('nonNumericProvider')]
    public function parseThrowsOnNonNumericValue(string $bad): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid monetary amount/i');
        Money::parse($bad);
    }

    public static function nonNumericProvider(): array
    {
        return [
            'empty string'   => [''],
            'plain text'     => ['abc'],
            'partial number' => ['12.34.56'],
            'NaN'            => ['NaN'],
            'hex number'     => ['0x1F'],
        ];
    }

    // -------------------------------------------------------------------------
    // Money::fee()
    // -------------------------------------------------------------------------

    #[Test]
    public function feeComputesCorrectly(): void
    {
        // 0.25% of 1500.00 = 3.75 exactly
        $this->assertSame('3.75', Money::fee('1500.00', '0.0025'));
    }

    #[Test]
    public function feeRoundsHalfUp(): void
    {
        // 0.25% of 100.00 = 0.25 exactly (no rounding needed)
        $this->assertSame('0.25', Money::fee('100.00', '0.0025'));
    }

    #[Test]
    public function feeRoundsHalfUpOnBoundary(): void
    {
        // 0.25% of 1.00 = 0.0025 → rounds half-up to 0.00
        $this->assertSame('0.00', Money::fee('1.00', '0.0025'));
    }

    #[Test]
    public function feeOnLargeAmountRoundsCorrectly(): void
    {
        // 0.25% of 75000.00 = 187.50 exactly
        $this->assertSame('187.50', Money::fee('75000.00', '0.0025'));
    }

    #[Test]
    public function feeOnAmountThatProducesMidpointRoundsUp(): void
    {
        // 0.25% of 9999.99 = 24.999975 → rounds half-up to 25.00
        $this->assertSame('25.00', Money::fee('9999.99', '0.0025'));
    }

    #[Test]
    public function feeThrowsOnNonNumericAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fee('abc', '0.0025');
    }

    #[Test]
    public function feeThrowsOnNonNumericRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fee('100.00', 'xyz');
    }

    #[Test]
    public function feeRespectsCustomScale(): void
    {
        // 0.25% of 1000.00 = 2.50 (at scale 4 → "2.5000")
        $this->assertSame('2.5000', Money::fee('1000.00', '0.0025', scale: 4));
    }

    // -------------------------------------------------------------------------
    // Money::subtract()
    // -------------------------------------------------------------------------

    #[Test]
    public function subtractComputesCorrectly(): void
    {
        // 1500.00 - 3.75 = 1496.25
        $this->assertSame('1496.25', Money::subtract('1500.00', '3.75'));
    }

    #[Test]
    public function subtractLargeAmounts(): void
    {
        // 75000.00 - 187.50 = 74812.50
        $this->assertSame('74812.50', Money::subtract('75000.00', '187.50'));
    }

    #[Test]
    public function subtractProducesNegativeResult(): void
    {
        $this->assertSame('-5.00', Money::subtract('0.00', '5.00'));
    }

    #[Test]
    public function subtractThrowsOnNonNumericMinuend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::subtract('abc', '5.00');
    }

    #[Test]
    public function subtractThrowsOnNonNumericSubtrahend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::subtract('100.00', 'xyz');
    }

    // -------------------------------------------------------------------------
    // Money::round()
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('roundHalfUpProvider')]
    public function roundHalfUp(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::round($input));
    }

    public static function roundHalfUpProvider(): array
    {
        return [
            'already rounded'           => ['1500.00', '1500.00'],
            'rounds up at .5'           => ['2.505',   '2.51'],
            'rounds down below .5'      => ['2.504',   '2.50'],
            'rounds up ties half-up'    => ['2.555',   '2.56'],
            'integer string'            => ['100',     '100.00'],
            'negative rounds half-up'   => ['-2.505',  '-2.51'],  // HALF_UP rounds toward +∞ → -2.505 → -2.51
        ];
    }

    #[Test]
    public function roundThrowsOnNonNumericInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::round('not-a-number');
    }

    // -------------------------------------------------------------------------
    // Money::compare()
    // -------------------------------------------------------------------------

    #[Test]
    public function compareReturnsNegativeWhenFirstIsSmaller(): void
    {
        $this->assertSame(-1, Money::compare('1.00', '2.00'));
    }

    #[Test]
    public function compareReturnsZeroWhenEqual(): void
    {
        $this->assertSame(0, Money::compare('1.00', '1.0'));
    }

    #[Test]
    public function compareReturnsPositiveWhenFirstIsLarger(): void
    {
        $this->assertSame(1, Money::compare('10000.00', '9999.99'));
    }

    // -------------------------------------------------------------------------
    // Money::isPositive()
    // -------------------------------------------------------------------------

    #[Test]
    public function isPositiveReturnsTrueForPositiveAmount(): void
    {
        $this->assertTrue(Money::isPositive('0.01'));
    }

    #[Test]
    public function isPositiveReturnsFalseForZero(): void
    {
        $this->assertFalse(Money::isPositive('0'));
    }

    #[Test]
    public function isPositiveReturnsFalseForNegative(): void
    {
        $this->assertFalse(Money::isPositive('-100.00'));
    }

    #[Test]
    public function isPositiveThrowsOnNonNumericInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::isPositive('not-a-number');
    }

    // -------------------------------------------------------------------------
    // Integration: fee + subtract end-to-end (simulating settlement)
    // -------------------------------------------------------------------------

    #[Test]
    public function settlementMathIsCorrectForSampleTransaction(): void
    {
        // TXN001: amount=1500.00, rate=0.25%
        $amount = '1500.00';
        $rate   = '0.0025';
        $fee    = Money::fee($amount, $rate);
        $net    = Money::subtract($amount, $fee);

        $this->assertSame('3.75', $fee);
        $this->assertSame('1496.25', $net);
    }

    #[Test]
    public function settlementMathIsCorrectForLargeTransaction(): void
    {
        // TXN005: amount=75000.00, rate=0.25%
        $amount = '75000.00';
        $rate   = '0.0025';
        $fee    = Money::fee($amount, $rate);
        $net    = Money::subtract($amount, $fee);

        $this->assertSame('187.50', $fee);
        $this->assertSame('74812.50', $net);
    }
}
