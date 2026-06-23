<?php

declare(strict_types=1);

namespace BankingPipeline\Config;

/**
 * Fraud detector rule constants.
 *
 * All thresholds, weights, and the risk-score cutoff live here so they can be
 * reviewed and changed in one place without touching rule logic.
 *
 * The fraud detector uses a weighted additive model:
 *   - Each triggered rule adds its weight to the running score.
 *   - If the final score >= CUTOFF the transaction is high-risk (rejected).
 *   - Otherwise it is forwarded to settlement.
 */
final class FraudRules
{
    // -------------------------------------------------------------------------
    // Rule weights — points added when the rule fires
    // -------------------------------------------------------------------------

    /**
     * Points added when the transaction amount is >= HIGH_VALUE_THRESHOLD.
     * Default: +40 points.
     */
    public const WEIGHT_HIGH_VALUE = 40;

    /**
     * Points added when the transaction timestamp falls in the overnight window
     * (hour >= OVERNIGHT_HOUR_START and hour <= OVERNIGHT_HOUR_END).
     * Default: +30 points.
     */
    public const WEIGHT_UNUSUAL_HOUR = 30;

    /**
     * Points added when metadata.country differs from HOME_COUNTRY
     * (or when the country field is absent — cannot confirm it is domestic).
     * Default: +30 points.
     */
    public const WEIGHT_CROSS_BORDER = 30;

    // -------------------------------------------------------------------------
    // Rule thresholds
    // -------------------------------------------------------------------------

    /**
     * Minimum amount (as a string decimal, applied per-currency face value)
     * that triggers the high-value rule.
     * Default: "10000.00" (i.e. $10,000 USD face value, or equivalent units
     * in other currencies — no FX conversion is applied).
     */
    public const HIGH_VALUE_THRESHOLD = '10000.00';

    /**
     * First hour of the overnight window (inclusive, 24-hour clock).
     * Default: 0 → midnight.
     */
    public const OVERNIGHT_HOUR_START = 0;

    /**
     * Last hour of the overnight window (inclusive, 24-hour clock).
     * Default: 5 → 05:xx (so hours 0, 1, 2, 3, 4, 5 all trigger; 6 does not).
     */
    public const OVERNIGHT_HOUR_END = 5;

    /**
     * The institution's home country code.
     * Transactions where metadata.country matches this value are NOT flagged
     * as cross-border. All other values (including absent country) are flagged.
     * Default: "US".
     */
    public const HOME_COUNTRY = 'US';

    // -------------------------------------------------------------------------
    // Score cutoff
    // -------------------------------------------------------------------------

    /**
     * Minimum risk score that marks a transaction high-risk.
     * Score >= CUTOFF → rejected; score < CUTOFF → forwarded to settlement.
     * Default: 60.
     */
    public const CUTOFF = 60;

    // -------------------------------------------------------------------------
    // Rule identifiers (human-readable names recorded in rejection reasons)
    // -------------------------------------------------------------------------

    /** Rule name used in the rejection reason string for the high-value rule. */
    public const RULE_HIGH_VALUE = 'high_value';

    /** Rule name used in the rejection reason string for the unusual-hour rule. */
    public const RULE_UNUSUAL_HOUR = 'unusual_hour';

    /** Rule name used in the rejection reason string for the cross-border rule. */
    public const RULE_CROSS_BORDER = 'cross_border';

    /** Prevent instantiation — this class is a constants container only. */
    private function __construct() {}
}
