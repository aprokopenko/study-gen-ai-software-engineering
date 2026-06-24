<?php

declare(strict_types=1);

namespace BankingPipeline\Config;

/**
 * Settlement stage constants.
 *
 * All fee rates and settlement parameters live here so they can be reviewed
 * and changed in one place without touching business logic.
 *
 * Fee calculation:
 *   fee = round(amount × FEE_RATE, 2, HALF_UP)
 *   net = amount − fee
 *
 * Reconciliation rule:
 *   The fee is rounded half-up to the currency minor unit (2 decimal places)
 *   first. The net is then computed as (amount − rounded_fee), also rounded
 *   half-up to 2 decimal places via Money::subtract(). In practice, because
 *   amount is already a 2-decimal string, net = amount − fee is exact with
 *   no additional rounding. In edge cases where amount has more precision,
 *   a rounding difference of at most 1 cent (±0.01) between the sum
 *   (fee + net) and the original amount is possible and acceptable — the fee
 *   is authoritative (rounded first) and the customer receives the remainder.
 */
final class SettlementConfig
{
    /**
     * Settlement fee rate as a decimal string.
     *
     * Default: 0.25% expressed as "0.0025".
     * Applied as: fee = amount × FEE_RATE, rounded half-up to the minor unit.
     *
     * To change the fee rate, update this constant only. The Settlement stage
     * reads it at runtime — no other code needs to change.
     */
    public const FEE_RATE = '0.0025';

    /**
     * Human-readable description of the fee rate (for documentation and logs).
     * Kept in sync with FEE_RATE.
     */
    public const FEE_RATE_DESCRIPTION = '0.25%';

    /** Prevent instantiation — this class is a constants container only. */
    private function __construct() {}
}
