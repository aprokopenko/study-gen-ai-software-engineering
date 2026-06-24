<?php

declare(strict_types=1);

namespace BankingPipeline\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Math\Exception\MathException;
use InvalidArgumentException;

/**
 * Precise decimal money arithmetic.
 *
 * All amounts are kept as strings — never float. Uses brick/math (BigDecimal)
 * backed by the bcmath PHP extension for arbitrary-precision arithmetic.
 *
 * The minor unit for all currencies in the sample set is 2 decimal places.
 */
final class Money
{
    /** Default decimal places (currency minor unit) for the sample set. */
    public const DEFAULT_SCALE = 2;

    /**
     * Parse a string amount to a canonical decimal string.
     *
     * Validates that the value is numeric. Does NOT enforce positivity
     * (that is the Validator stage's responsibility).
     *
     * @throws InvalidArgumentException if the value is not a valid decimal.
     */
    public static function parse(string $amount): string
    {
        try {
            $decimal = BigDecimal::of($amount);
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Invalid monetary amount: '{$amount}'. Must be a valid decimal string.",
                previous: $e,
            );
        }

        // Return a canonical representation (no trailing zeros stripped — keep as-is)
        return (string) $decimal;
    }

    /**
     * Compute a fee by multiplying an amount by a rate.
     *
     * Example: fee('1500.00', '0.0025') → '3.75' (0.25% of 1500.00)
     *
     * The result is rounded half-up to $scale decimal places.
     *
     * @param string $amount  The base amount string (e.g. "1500.00").
     * @param string $rate    The fee rate as a decimal string (e.g. "0.0025" for 0.25%).
     * @param int    $scale   Decimal places for the currency minor unit (default 2).
     * @throws InvalidArgumentException on non-numeric input.
     */
    public static function fee(string $amount, string $rate, int $scale = self::DEFAULT_SCALE): string
    {
        try {
            $result = BigDecimal::of($amount)
                ->multipliedBy(BigDecimal::of($rate))
                ->toScale($scale, RoundingMode::HALF_UP);
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Cannot compute fee for amount '{$amount}' with rate '{$rate}'.",
                previous: $e,
            );
        }

        return (string) $result;
    }

    /**
     * Subtract one amount from another.
     *
     * The result is rounded half-up to $scale decimal places.
     *
     * @param string $amount    The minuend (e.g. "1500.00").
     * @param string $subtract  The subtrahend (e.g. "3.75").
     * @param int    $scale     Decimal places for the currency minor unit (default 2).
     * @throws InvalidArgumentException on non-numeric input.
     */
    public static function subtract(string $amount, string $subtract, int $scale = self::DEFAULT_SCALE): string
    {
        try {
            $result = BigDecimal::of($amount)
                ->minus(BigDecimal::of($subtract))
                ->toScale($scale, RoundingMode::HALF_UP);
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Cannot subtract '{$subtract}' from '{$amount}'.",
                previous: $e,
            );
        }

        return (string) $result;
    }

    /**
     * Round an amount string to $scale decimal places using half-up rounding.
     *
     * @param string $amount  The decimal string to round.
     * @param int    $scale   Decimal places (default 2).
     * @throws InvalidArgumentException on non-numeric input.
     */
    public static function round(string $amount, int $scale = self::DEFAULT_SCALE): string
    {
        try {
            $result = BigDecimal::of($amount)->toScale($scale, RoundingMode::HALF_UP);
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Cannot round amount '{$amount}'.",
                previous: $e,
            );
        }

        return (string) $result;
    }

    /**
     * Compare two amount strings.
     *
     * Returns -1 if $a < $b, 0 if equal, 1 if $a > $b.
     *
     * @throws InvalidArgumentException on non-numeric input.
     */
    public static function compare(string $a, string $b): int
    {
        try {
            return BigDecimal::of($a)->compareTo(BigDecimal::of($b));
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Cannot compare '{$a}' and '{$b}'.",
                previous: $e,
            );
        }
    }

    /**
     * Return true if $amount is strictly greater than zero.
     *
     * @throws InvalidArgumentException on non-numeric input.
     */
    public static function isPositive(string $amount): bool
    {
        try {
            return BigDecimal::of($amount)->isPositive();
        } catch (MathException $e) {
            throw new InvalidArgumentException(
                "Cannot determine sign of amount '{$amount}'.",
                previous: $e,
            );
        }
    }
}
