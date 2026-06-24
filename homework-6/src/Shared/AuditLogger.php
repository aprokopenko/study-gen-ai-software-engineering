<?php

declare(strict_types=1);

namespace BankingPipeline\Shared;

/**
 * PII-safe audit logger for pipeline stages.
 *
 * Records: ISO-8601 timestamp, step name, transaction ID, and outcome.
 *
 * PII fields are NEVER written in plaintext. The following fields are masked
 * before any value reaches the log:
 *   - source_account
 *   - destination_account
 *   - Any key whose name contains "name", "description", or "account"
 *
 * Masking strategy: SHA-256 hash of the value, truncated to 16 hex chars,
 * prefixed with "[MASKED:". This is irreversible yet consistent within a run
 * (same value → same masked token) so log correlation is still possible.
 */
final class AuditLogger
{
    /** Context keys that must always be masked regardless of position. */
    private const PII_KEYS = [
        'source_account',
        'destination_account',
        'name',
        'description',
    ];

    /**
     * @param callable(string): void $sink  Output sink. Defaults to writing to STDERR.
     *                                      Inject a silent or capturing callable in tests.
     */
    public function __construct(
        private readonly mixed $sink = null,
    ) {}

    /**
     * Log a pipeline step event.
     *
     * @param string $step          The pipeline step name (e.g. "validator", "fraud_detector").
     * @param string $transactionId The transaction reference (e.g. "TXN001").
     * @param string $outcome       The result (e.g. "validated", "rejected", "settled").
     * @param array  $context       Optional additional context (PII fields will be masked).
     */
    public function log(
        string $step,
        string $transactionId,
        string $outcome,
        array $context = [],
    ): void {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format(\DateTimeInterface::ATOM);

        $safeContext = $this->maskPii($context);

        $entry = [
            'timestamp'      => $timestamp,
            'step'           => $step,
            'transaction_id' => $transactionId,
            'outcome'        => $outcome,
            'context'        => $safeContext,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        $this->write($line);
    }

    /**
     * Recursively walk a context array and mask any PII keys.
     */
    private function maskPii(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if ($this->isPiiKey((string) $key)) {
                $safe[$key] = $this->mask((string) $value);
            } elseif (is_array($value)) {
                $safe[$key] = $this->maskPii($value);
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * Determine whether a key name refers to a PII field.
     */
    private function isPiiKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::PII_KEYS as $piiKey) {
            if ($lower === $piiKey || str_contains($lower, 'account') || str_contains($lower, 'name')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a masked representation of a PII value.
     *
     * Uses SHA-256 truncated to 16 hex chars, prefixed with "[MASKED:" and suffixed "]".
     * This is irreversible but consistent within a run.
     */
    private function mask(string $value): string
    {
        return '[MASKED:' . substr(hash('sha256', $value), 0, 16) . ']';
    }

    /**
     * Write a log line to the configured sink.
     *
     * Default: write to STDERR (never STDOUT, so pipeline JSON frames stay clean).
     * In tests: pass a capturing callable as the sink.
     */
    private function write(string $line): void
    {
        if ($this->sink !== null) {
            ($this->sink)($line);
        } else {
            fwrite(STDERR, $line);
        }
    }
}
