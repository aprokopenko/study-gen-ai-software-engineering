<?php

declare(strict_types=1);

namespace BankingPipeline\Pipeline;

use BankingPipeline\Shared\Envelope;

/**
 * Run summary reporter.
 *
 * Reads all final result files from `results/` (excluding its own summary files),
 * computes totals and a rejection-reason breakdown, writes both a structured
 * `summary.json` and a human-readable `summary.txt`, and returns the structured
 * array so the MCP server (Task 8) can serve it directly.
 *
 * ## Reason grouping scheme
 *
 * Raw rejection reasons from the pipeline are normalised into four canonical
 * categories to produce a meaningful breakdown (not one-per-unique-string):
 *
 *   - `missing-field`       — validator: one or more required fields absent
 *   - `non-positive-amount` — validator: amount is zero or negative
 *   - `invalid-currency`    — validator: currency code not in ISO 4217
 *   - `high-risk`           — fraud detector: risk score >= cutoff
 *
 * Reasons that do not match any known pattern are grouped under `unknown`.
 * The raw reason string is preserved inside the breakdown entry for auditing.
 *
 * ## Summary-file exclusion
 *
 * When the reporter is called a second time (e.g. by the MCP server), its own
 * `summary.json` and `summary.txt` files are already present in `results/`.
 * The reporter skips any filename that is `summary.json` or `summary.txt` so
 * it never counts those as transaction results.
 *
 * ## Malformed file handling
 *
 * If a result file cannot be read or parsed, it is skipped and its filename is
 * recorded in the `errors` array of the summary. This avoids a silent miscount
 * while still producing a summary with the remaining files. Callers can inspect
 * `errors` to detect partial data.
 *
 * ## Reconciliation invariant
 *
 *   settled_count + rejected_count === total_processed
 *
 * This is always satisfied (or the input data is inconsistent — recorded as an error).
 */
final class Reporter
{
    /** Filenames the reporter writes; excluded from transaction counting. */
    private const SUMMARY_FILES = ['summary.json', 'summary.txt'];

    /**
     * @param string $resultsDir Absolute path to the results directory.
     *                           Inject a temp dir in tests; defaults to nothing —
     *                           the caller (CLI / MCP server) supplies the real path.
     */
    public function __construct(
        private readonly string $resultsDir,
    ) {}

    /**
     * Summarise all completed transactions in the results directory.
     *
     * @return array{
     *   total_processed: int,
     *   settled_count: int,
     *   rejected_count: int,
     *   rejection_breakdown: array<string, int>,
     *   errors: string[],
     *   generated_at: string,
     * }
     */
    public function summarize(): array
    {
        [$envelopes, $errors] = $this->readResultFiles();

        $settled  = 0;
        $rejected = 0;
        $breakdown = [];   // category => count

        foreach ($envelopes as $envelope) {
            $status = $envelope->data['status'] ?? null;

            if ($status === 'settled') {
                $settled++;
            } elseif ($status === 'rejected') {
                $rejected++;
                $reason   = (string) ($envelope->data['reason'] ?? '');
                $category = $this->categorizeReason($reason);
                $breakdown[$category] = ($breakdown[$category] ?? 0) + 1;
            }
            // Entries with any other status are counted in total but do not increment
            // settled or rejected — they surface only in the reconciliation below.
        }

        $total = $settled + $rejected;

        $summary = [
            'total_processed'    => $total,
            'settled_count'      => $settled,
            'rejected_count'     => $rejected,
            'rejection_breakdown' => $breakdown,
            'errors'             => $errors,
            'generated_at'       => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format(\DateTimeInterface::ATOM),
        ];

        $this->writeSummaryJson($summary);
        $this->writeSummaryTxt($summary);

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Read all transaction result files from the results directory.
     *
     * Skips `summary.json` / `summary.txt` (the reporter's own output files) and
     * any file that cannot be parsed. Returns a tuple [envelopes[], errors[]].
     *
     * @return array{Envelope[], string[]}
     */
    private function readResultFiles(): array
    {
        $envelopes = [];
        $errors    = [];

        if (!is_dir($this->resultsDir)) {
            // No results directory at all — treat as zero results, no error.
            return [$envelopes, $errors];
        }

        $files = glob($this->resultsDir . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            return [$envelopes, $errors];
        }

        foreach ($files as $filePath) {
            $filename = basename($filePath);

            // Skip the reporter's own summary files.
            if (in_array($filename, self::SUMMARY_FILES, strict: true)) {
                continue;
            }

            $json = @file_get_contents($filePath);
            if ($json === false) {
                $errors[] = "Cannot read file: {$filename}";
                continue;
            }

            try {
                $envelopes[] = Envelope::fromJson($json);
            } catch (\Throwable $e) {
                $errors[] = "Malformed envelope in {$filename}: {$e->getMessage()}";
            }
        }

        return [$envelopes, $errors];
    }

    /**
     * Normalise a raw rejection reason string into a canonical category.
     *
     * Pattern-matching order matters: more-specific patterns first.
     *
     * | Category            | Trigger pattern (case-insensitive)                    |
     * |---------------------|------------------------------------------------------|
     * | missing-field       | "missing" or "required field" or "required"          |
     * | non-positive-amount | "amount" + ("zero" or "positive" or "non-positive"   |
     * |                     |  or "greater than")                                  |
     * | invalid-currency    | "currency" or "iso 4217"                             |
     * | high-risk           | "high-risk" or "high risk" or "risk" + "score"       |
     * | unknown             | anything else                                        |
     */
    private function categorizeReason(string $reason): string
    {
        $lower = strtolower($reason);

        // Missing required field
        if (
            str_contains($lower, 'missing') ||
            str_contains($lower, 'required field') ||
            (str_contains($lower, 'required') && str_contains($lower, 'field'))
        ) {
            return 'missing-field';
        }

        // Non-positive amount
        if (
            str_contains($lower, 'amount') &&
            (
                str_contains($lower, 'zero') ||
                str_contains($lower, 'positive') ||
                str_contains($lower, 'greater than')
            )
        ) {
            return 'non-positive-amount';
        }

        // Invalid / unrecognised currency
        if (
            str_contains($lower, 'currency') ||
            str_contains($lower, 'iso 4217')
        ) {
            return 'invalid-currency';
        }

        // Fraud-detector high-risk
        if (
            str_contains($lower, 'high-risk') ||
            str_contains($lower, 'high risk') ||
            (str_contains($lower, 'risk') && str_contains($lower, 'score'))
        ) {
            return 'high-risk';
        }

        return 'unknown';
    }

    /**
     * Write the structured summary to `summary.json` in the results directory.
     */
    private function writeSummaryJson(array $summary): void
    {
        $this->ensureResultsDirExists();

        $path = $this->resultsDir . DIRECTORY_SEPARATOR . 'summary.json';
        $json = json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Cannot write summary.json to: {$this->resultsDir}");
        }
    }

    /**
     * Write a human-readable summary to `summary.txt` in the results directory.
     */
    private function writeSummaryTxt(array $summary): void
    {
        $this->ensureResultsDirExists();

        $path  = $this->resultsDir . DIRECTORY_SEPARATOR . 'summary.txt';
        $lines = [];

        $lines[] = '=== Banking Pipeline Run Summary ===';
        $lines[] = sprintf('Generated at : %s', $summary['generated_at']);
        $lines[] = '';
        $lines[] = sprintf('Total processed : %d', $summary['total_processed']);
        $lines[] = sprintf('Settled         : %d', $summary['settled_count']);
        $lines[] = sprintf('Rejected        : %d', $summary['rejected_count']);

        if (!empty($summary['rejection_breakdown'])) {
            $lines[] = '';
            $lines[] = 'Rejection breakdown:';
            foreach ($summary['rejection_breakdown'] as $category => $count) {
                $lines[] = sprintf('  %-24s %d', $category . ':', $count);
            }
        }

        if (!empty($summary['errors'])) {
            $lines[] = '';
            $lines[] = 'Skipped files (errors):';
            foreach ($summary['errors'] as $err) {
                $lines[] = '  - ' . $err;
            }
        }

        $lines[] = '';

        $text = implode(PHP_EOL, $lines);

        if (file_put_contents($path, $text) === false) {
            throw new \RuntimeException("Cannot write summary.txt to: {$this->resultsDir}");
        }
    }

    /**
     * Create the results directory if it does not yet exist.
     *
     * Needed when summarize() is called before any run has produced result files
     * (zero-results edge case) — we still need to write the summary files.
     */
    private function ensureResultsDirExists(): void
    {
        if (!is_dir($this->resultsDir)) {
            if (!mkdir($this->resultsDir, 0755, recursive: true)) {
                throw new \RuntimeException("Cannot create results directory: {$this->resultsDir}");
            }
        }
    }
}
