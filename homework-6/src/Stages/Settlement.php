<?php

declare(strict_types=1);

namespace BankingPipeline\Stages;

use BankingPipeline\Config\SettlementConfig;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Shared\Money;

/**
 * Settlement pipeline stage.
 *
 * Reads low-risk messages forwarded by the FraudDetector (from shared/output,
 * target=settlement), computes a percentage fee and net amount, and writes the
 * final outcome to shared/results with status=settled.
 *
 * Fee calculation (see SettlementConfig for the reconciliation rule):
 *   fee = round(amount × FEE_RATE, 2, HALF_UP)   — uses Money::fee()
 *   net = amount − fee                            — uses Money::subtract()
 *
 * Both fee and net are stored as strings. All arithmetic uses brick/math
 * (BigDecimal) via the Money helper — never float.
 *
 * The core calculation logic lives in process() for independent testability.
 * File I/O is orchestrated around it in run(), mirroring the Validator and
 * FraudDetector pattern.
 */
final class Settlement
{
    private const STAGE_NAME = 'settlement';

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
     * Run the settlement stage over all files currently in shared/output
     * whose target is "settlement" (written there by the FraudDetector).
     *
     * For each file:
     *   1. Read the envelope from output/.
     *   2. Move it to processing/ while working (atomic rename).
     *   3. Call process() to compute fee/net and build the settled message.
     *   4. Write the result envelope to results/ with status=settled.
     *   5. Delete the processing/ copy.
     *
     * @return array{settled: int} Count of settled transactions.
     */
    public function run(): array
    {
        $counts = ['settled' => 0];

        foreach ($this->queue->listFiles(FileQueue::DIR_OUTPUT) as $filename) {
            // 1+2: Read then claim the file by moving it to processing
            $envelope = $this->queue->read($filename, FileQueue::DIR_OUTPUT);
            $this->queue->move($filename, FileQueue::DIR_OUTPUT, FileQueue::DIR_PROCESSING);

            // 3: Pure computation — no file I/O
            $resultData = $this->process($envelope->data);

            // 4: Write the result envelope to results/
            $resultEnvelope = Envelope::create(
                source: self::STAGE_NAME,
                target: 'results',
                type: $envelope->type,
                data: $resultData,
            );

            $this->queue->write(FileQueue::DIR_RESULTS, $resultEnvelope);

            // 5: Delete the processing copy — work is complete
            $this->deleteFromProcessing($filename);

            $counts['settled']++;
        }

        return $counts;
    }

    /**
     * Compute the settlement for a fraud-checked transaction.
     *
     * Calculates the fee and net amount using Money helpers (brick/math,
     * half-up rounding to 2 decimal places) and enriches the message with
     * status=settled, fee, and net as strings.
     *
     * This method contains the pure calculation logic — it does NOT touch the
     * file system. This makes it independently unit-testable.
     *
     * Fee computation:
     *   fee = round(amount × FEE_RATE, 2, HALF_UP)
     *   net = round(amount − fee, 2, HALF_UP)
     *
     * Reconciliation rule (from SettlementConfig): fee is authoritative;
     * net = amount − rounded_fee. When amount is a 2-decimal string (the
     * normal validated case), fee + net == amount exactly. See SettlementConfig
     * for the documented edge-case exception.
     *
     * @param array<string, mixed> $message  The transaction data (the `data` field of the envelope).
     * @return array<string, mixed>          The same data enriched with `status`, `fee`, and `net`.
     */
    public function process(array $message): array
    {
        $amount = (string) ($message['amount'] ?? '0');

        // Compute fee: amount × rate, rounded half-up to 2 decimal places
        $fee = Money::fee($amount, SettlementConfig::FEE_RATE);

        // Compute net: amount − fee, rounded half-up to 2 decimal places
        // When amount is a standard 2-decimal string, this is always exact.
        $net = Money::subtract($amount, $fee);

        $message['status'] = 'settled';
        $message['fee']    = $fee;
        $message['net']    = $net;

        $this->logger->log(
            step: self::STAGE_NAME,
            transactionId: (string) ($message['transaction_id'] ?? 'unknown'),
            outcome: 'settled',
            context: [
                'amount'   => $amount,
                'fee'      => $fee,
                'net'      => $net,
                'currency' => (string) ($message['currency'] ?? ''),
            ],
        );

        return $message;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

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
