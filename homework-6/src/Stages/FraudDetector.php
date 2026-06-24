<?php

declare(strict_types=1);

namespace BankingPipeline\Stages;

use BankingPipeline\Config\FraudRules;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Shared\Money;

/**
 * Fraud detector pipeline stage.
 *
 * Reads validated messages from shared/output (written there by the Validator),
 * computes a weighted additive risk score, and either:
 *   - Rejects the transaction to shared/results (status=rejected, with reason)
 *     when the score >= FraudRules::CUTOFF, OR
 *   - Passes it forward to settlement via shared/output (target=settlement)
 *     when the score < FraudRules::CUTOFF.
 *
 * Scoring rules (all configured in FraudRules):
 *   - High value:     amount >= HIGH_VALUE_THRESHOLD                    → +WEIGHT_HIGH_VALUE
 *   - Unusual hour:   timestamp hour in [OVERNIGHT_HOUR_START..END]     → +WEIGHT_UNUSUAL_HOUR
 *   - Cross-border:   metadata.country != HOME_COUNTRY (or absent)      → +WEIGHT_CROSS_BORDER
 *
 * The core scoring logic lives in process() for independent testability.
 * File I/O is orchestrated around it in run(), mirroring the Validator pattern.
 */
final class FraudDetector
{
    private const STAGE_NAME = 'fraud_detector';

    /**
     * @param FileQueue   $queue    The file queue rooted at the shared/ base directory.
     * @param AuditLogger $logger   PII-safe audit logger.
     * @param string      $baseDir  Absolute path to the shared/ directory (for cleanup of processing/).
     */
    public function __construct(
        private readonly FileQueue $queue,
        private readonly AuditLogger $logger,
        private readonly string $baseDir = '',
    ) {}

    /**
     * Run the fraud detector over all files currently in shared/output
     * (the hand-off directory from the Validator).
     *
     * For each file:
     *   1. Read the envelope from output/.
     *   2. Move it to processing/ while working (atomic rename).
     *   3. Call process() to score and decide.
     *   4. Write the result envelope to output/ (low-risk, target=settlement)
     *      or results/ (high-risk, status=rejected).
     *   5. Delete the processing/ copy.
     *
     * @return array{passed: int, rejected: int} Counts of each outcome.
     */
    public function run(): array
    {
        $counts = ['passed' => 0, 'rejected' => 0];

        foreach ($this->queue->listFiles(FileQueue::DIR_OUTPUT) as $filename) {
            // 1+2: Read then claim the file by moving it to processing
            $envelope = $this->queue->read($filename, FileQueue::DIR_OUTPUT);
            $this->queue->move($filename, FileQueue::DIR_OUTPUT, FileQueue::DIR_PROCESSING);

            // 3: Pure scoring — no file I/O
            $resultData = $this->process($envelope->data);

            // Branch on risk_score rather than status, because the persisted
            // status for high-risk messages is 'rejected' (spec §3 line 146)
            // while low-risk messages carry 'fraud_checked'.
            $isHighRisk = $resultData['risk_score'] >= FraudRules::CUTOFF;

            // 4: Write the result envelope to the appropriate destination
            $resultEnvelope = Envelope::create(
                source: self::STAGE_NAME,
                target: $isHighRisk ? 'results' : 'settlement',
                type: $envelope->type,
                data: $resultData,
            );

            $destDir = $isHighRisk
                ? FileQueue::DIR_RESULTS
                : FileQueue::DIR_OUTPUT;

            $this->queue->write($destDir, $resultEnvelope);

            // 5: Delete the processing copy — work is complete
            $this->deleteFromProcessing($filename);

            $counts[$isHighRisk ? 'rejected' : 'passed']++;
        }

        return $counts;
    }

    /**
     * Compute the fraud risk score for a validated transaction and return the result.
     *
     * This method contains the pure scoring logic — it does NOT touch the file system.
     * This makes it independently unit-testable.
     *
     * @param array<string, mixed> $message  The transaction data (the `data` field of the envelope).
     * @return array<string, mixed>          The same data enriched with `risk_score`, `risk_reasons`,
     *                                       and `status` (and `reason` when high-risk).
     */
    public function process(array $message): array
    {
        $score   = 0;
        $reasons = [];

        // Rule 1 — High value: amount >= HIGH_VALUE_THRESHOLD
        $amount = (string) ($message['amount'] ?? '0');
        if (Money::compare($amount, FraudRules::HIGH_VALUE_THRESHOLD) >= 0) {
            $score   += FraudRules::WEIGHT_HIGH_VALUE;
            $reasons[] = FraudRules::RULE_HIGH_VALUE;
        }

        // Rule 2 — Unusual hour: timestamp hour in overnight window [START..END]
        $timestamp = (string) ($message['timestamp'] ?? '');
        if ($this->isOvernightHour($timestamp)) {
            $score   += FraudRules::WEIGHT_UNUSUAL_HOUR;
            $reasons[] = FraudRules::RULE_UNUSUAL_HOUR;
        }

        // Rule 3 — Cross-border: metadata.country absent OR != HOME_COUNTRY
        $country = $message['metadata']['country'] ?? null;
        if ($country === null || (string) $country !== FraudRules::HOME_COUNTRY) {
            $score   += FraudRules::WEIGHT_CROSS_BORDER;
            $reasons[] = FraudRules::RULE_CROSS_BORDER;
        }

        // Record score and reasons regardless of outcome
        $message['risk_score']   = $score;
        $message['risk_reasons'] = $reasons;

        // Decide: high-risk if score >= cutoff
        if ($score >= FraudRules::CUTOFF) {
            return $this->flagHighRisk($message, $score, $reasons);
        }

        return $this->flagLowRisk($message, $score, $reasons);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Mark a transaction high-risk and log the outcome.
     *
     * The persisted status is 'rejected' (spec §3: "high-risk transactions both
     * use `rejected`") so the Reporter and MCP server see a uniform shape.
     * The audit log outcome remains 'high_risk' for internal observability.
     *
     * @param array<string, mixed> $message
     * @param string[]             $reasons
     * @return array<string, mixed>
     */
    private function flagHighRisk(array $message, int $score, array $reasons): array
    {
        $reasonString = $this->buildReasonString($reasons, $score);

        $message['status'] = 'rejected';
        $message['reason'] = $reasonString;

        $this->logger->log(
            step: self::STAGE_NAME,
            transactionId: (string) ($message['transaction_id'] ?? 'unknown'),
            outcome: 'high_risk',
            context: [
                'risk_score'   => $score,
                'risk_reasons' => $reasons,
            ],
        );

        return $message;
    }

    /**
     * Mark a transaction low-risk and log the outcome.
     *
     * @param array<string, mixed> $message
     * @param string[]             $reasons
     * @return array<string, mixed>
     */
    private function flagLowRisk(array $message, int $score, array $reasons): array
    {
        $message['status'] = 'fraud_checked';

        $this->logger->log(
            step: self::STAGE_NAME,
            transactionId: (string) ($message['transaction_id'] ?? 'unknown'),
            outcome: 'fraud_checked',
            context: [
                'risk_score'   => $score,
                'risk_reasons' => $reasons,
            ],
        );

        return $message;
    }

    /**
     * Determine whether a timestamp's hour falls in the overnight window.
     *
     * Parses the ISO-8601 timestamp and reads its hour component.
     * Returns false (does not trigger) when the timestamp is unparseable.
     *
     * Window: [OVERNIGHT_HOUR_START .. OVERNIGHT_HOUR_END], both inclusive.
     * Default: hours 0–5 (i.e. 00:xx–05:xx triggers; 06:xx does not).
     */
    private function isOvernightHour(string $timestamp): bool
    {
        if ($timestamp === '') {
            return false;
        }

        try {
            $dt = new \DateTimeImmutable($timestamp);
        } catch (\Exception) {
            return false;
        }

        $hour = (int) $dt->format('G'); // 0–23, no leading zero

        return $hour >= FraudRules::OVERNIGHT_HOUR_START
            && $hour <= FraudRules::OVERNIGHT_HOUR_END;
    }

    /**
     * Build the human-readable reason string for a high-risk rejection.
     *
     * Format: "High-risk transaction: rules=[high_value, unusual_hour], score=70"
     *
     * @param string[] $reasons
     */
    private function buildReasonString(array $reasons, int $score): string
    {
        $ruleList = implode(', ', $reasons);
        return "High-risk transaction: rules=[{$ruleList}], score={$score}";
    }

    /**
     * Delete a file from the processing directory.
     *
     * Silently ignores missing files — idempotent cleanup.
     */
    private function deleteFromProcessing(string $filename): void
    {
        if ($this->baseDir === '') {
            return;
        }

        $path = rtrim($this->baseDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . FileQueue::DIR_PROCESSING
            . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
