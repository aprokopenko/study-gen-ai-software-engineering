<?php

declare(strict_types=1);

namespace BankingPipeline\Mcp;

use BankingPipeline\Shared\Envelope;

/**
 * Transport-agnostic data-access class for the MCP pipeline-status server.
 *
 * Reads final result files from the `results/` directory and serves answers to:
 *   - get_transaction_status(transaction_id) — settled/rejected + relevant fields
 *   - list_pipeline_results()               — summary list of all processed transactions
 *   - getPipelineSummary()                  — latest run summary (for pipeline://summary resource)
 *
 * The resultsDir is injected so tests can use a temp directory without touching
 * the real shared/results/ directory.
 *
 * ## Edge cases handled
 *   - Unknown transaction_id  → clean "not found" array, no exception
 *   - No run yet / empty dir  → empty list and a sensible empty summary, no crash
 *   - Missing/partial summary.json → degrade gracefully, return empty summary text
 */
final class PipelineStatusReader
{
    private const SUMMARY_JSON = 'summary.json';
    private const SUMMARY_TXT  = 'summary.txt';

    public function __construct(
        private readonly string $resultsDir,
    ) {}

    /**
     * Return the current status of a single transaction.
     *
     * On success returns an array with at least `found`, `transaction_id`,
     * `status`, plus either `fee`/`net` (settled) or `reason` (rejected).
     *
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $transactionId): array
    {
        if (trim($transactionId) === '') {
            return [
                'found'          => false,
                'transaction_id' => $transactionId,
                'message'        => 'transaction_id must not be empty',
            ];
        }

        $envelope = $this->findEnvelopeByTransactionId($transactionId);

        if ($envelope === null) {
            return [
                'found'          => false,
                'transaction_id' => $transactionId,
                'message'        => "No result found for transaction '{$transactionId}'",
            ];
        }

        $data   = $envelope->data;
        $status = $data['status'] ?? 'unknown';

        $result = [
            'found'          => true,
            'transaction_id' => $transactionId,
            'status'         => $status,
        ];

        if ($status === 'settled') {
            $result['amount']   = $data['amount']   ?? null;
            $result['currency'] = $data['currency'] ?? null;
            $result['fee']      = $data['fee']      ?? null;
            $result['net']      = $data['net']      ?? null;
        } elseif ($status === 'rejected') {
            $result['reason'] = $data['reason'] ?? null;
        }

        return $result;
    }

    /**
     * Return a summary list of all processed transactions (id + status).
     *
     * @return array{
     *   count: int,
     *   transactions: array<int, array{transaction_id: string, status: string}>
     * }
     */
    public function listPipelineResults(): array
    {
        $envelopes = $this->readAllResultEnvelopes();

        $transactions = [];
        foreach ($envelopes as $envelope) {
            $txnId  = $envelope->data['transaction_id'] ?? null;
            $status = $envelope->data['status']         ?? 'unknown';

            if ($txnId !== null) {
                $transactions[] = [
                    'transaction_id' => (string) $txnId,
                    'status'         => (string) $status,
                ];
            }
        }

        return [
            'count'        => count($transactions),
            'transactions' => $transactions,
        ];
    }

    /**
     * Return the latest run summary as a human-readable text string.
     *
     * Reads `summary.txt` if it exists; falls back to `summary.json` rendered
     * as formatted JSON; returns an empty-run placeholder if neither file exists.
     */
    public function getPipelineSummary(): string
    {
        $txtPath = $this->resultsDir . DIRECTORY_SEPARATOR . self::SUMMARY_TXT;
        if (is_file($txtPath)) {
            $content = @file_get_contents($txtPath);
            if ($content !== false && trim($content) !== '') {
                return $content;
            }
        }

        $jsonPath = $this->resultsDir . DIRECTORY_SEPARATOR . self::SUMMARY_JSON;
        if (is_file($jsonPath)) {
            $content = @file_get_contents($jsonPath);
            if ($content !== false && trim($content) !== '') {
                try {
                    $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
                    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    // Fall through to empty placeholder
                }
            }
        }

        return "No pipeline run summary available yet.\nRun the pipeline first: make run";
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Find the result envelope for a given transaction ID by scanning result files.
     *
     * Returns null if not found or the results directory is empty/absent.
     */
    private function findEnvelopeByTransactionId(string $transactionId): ?Envelope
    {
        foreach ($this->readAllResultEnvelopes() as $envelope) {
            $txnId = $envelope->data['transaction_id'] ?? null;
            if ((string) $txnId === $transactionId) {
                return $envelope;
            }
        }

        return null;
    }

    /**
     * Read and parse all transaction result files from the results directory.
     *
     * Skips the reporter's own summary files and any malformed envelope.
     *
     * @return Envelope[]
     */
    private function readAllResultEnvelopes(): array
    {
        if (!is_dir($this->resultsDir)) {
            return [];
        }

        $files = glob($this->resultsDir . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false || $files === []) {
            return [];
        }

        $envelopes = [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);

            // Skip reporter summary files — they are not transaction results.
            if (in_array($filename, [self::SUMMARY_JSON, self::SUMMARY_TXT], strict: true)) {
                continue;
            }

            $json = @file_get_contents($filePath);
            if ($json === false) {
                continue;
            }

            try {
                $envelopes[] = Envelope::fromJson($json);
            } catch (\Throwable) {
                // Silently skip malformed files — the MCP server should not crash on bad data.
            }
        }

        return $envelopes;
    }
}
