<?php

declare(strict_types=1);

namespace BankingPipeline\Stages;

use BankingPipeline\Config\Iso4217;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Shared\Money;
use InvalidArgumentException;

/**
 * Validator pipeline stage.
 *
 * Reads each transaction message from shared/input, moves it to processing
 * while working, then either:
 *   - Passes it forward as status=validated via shared/output (target=fraud_detector), OR
 *   - Rejects it to shared/results with status=rejected and a clear reason.
 *
 * Rejection conditions:
 *   - A required field is missing or empty.
 *   - amount is not a strictly positive decimal (rejects "-100.00", "0", non-numeric).
 *   - currency is not a valid ISO 4217 code (rejects "XYZ").
 *
 * Required fields: transaction_id, timestamp, source_account, destination_account,
 *                  amount, currency, transaction_type.
 *
 * The core validation logic lives in process() which takes a message array and
 * returns the decided message array. File I/O is orchestrated around it, making
 * the logic independently testable and reusable by the validator dry-run path (Task 12).
 */
final class Validator
{
    private const STAGE_NAME = 'validator';

    /** Required fields every transaction message must carry. */
    private const REQUIRED_FIELDS = [
        'transaction_id',
        'timestamp',
        'source_account',
        'destination_account',
        'amount',
        'currency',
        'transaction_type',
    ];

    /**
     * @param FileQueue   $queue    The file queue rooted at the shared/ base directory.
     * @param AuditLogger $logger   PII-safe audit logger.
     * @param string      $baseDir  Absolute path to the shared/ directory (for cleanup of processing/ files).
     */
    public function __construct(
        private readonly FileQueue $queue,
        private readonly AuditLogger $logger,
        private readonly string $baseDir = '',
    ) {}

    /**
     * Run the validator over all files currently in shared/input.
     *
     * For each file:
     *   1. Read the envelope from input/.
     *   2. Move it to processing/ while working (atomic rename).
     *   3. Call process() to decide validated vs. rejected.
     *   4. Write the result envelope to output/ (validated) or results/ (rejected).
     *   5. Delete the processing/ copy (work is done).
     *
     * @return array{validated: int, rejected: int} Counts of each outcome.
     */
    public function run(): array
    {
        $counts = ['validated' => 0, 'rejected' => 0];

        foreach ($this->queue->listFiles(FileQueue::DIR_INPUT) as $filename) {
            // 1+2: Read then claim the file by moving it to processing
            $envelope = $this->queue->read($filename, FileQueue::DIR_INPUT);
            $this->queue->move($filename, FileQueue::DIR_INPUT, FileQueue::DIR_PROCESSING);

            // 3: Pure validation — no file I/O
            $resultData = $this->process($envelope->data);

            $status = $resultData['status'];

            // 4: Write the result envelope to the appropriate destination
            $resultEnvelope = Envelope::create(
                source: self::STAGE_NAME,
                target: $status === 'validated' ? 'fraud_detector' : 'results',
                type: $envelope->type,
                data: $resultData,
            );

            $destDir = $status === 'validated'
                ? FileQueue::DIR_OUTPUT
                : FileQueue::DIR_RESULTS;

            $this->queue->write($destDir, $resultEnvelope);

            // 5: Delete the processing copy — work is complete
            $this->deleteFromProcessing($filename);

            $counts[$status === 'validated' ? 'validated' : 'rejected']++;
        }

        return $counts;
    }

    /**
     * Validate a raw transaction message array and return the result message.
     *
     * This method contains the pure validation logic — it does NOT touch the
     * file system. This makes it independently unit-testable and reusable by
     * the dry-run validate-transactions path (Task 12).
     *
     * @param array<string, mixed> $message  The transaction data (the `data` field of the envelope).
     * @return array<string, mixed>          The same data enriched with `status` and optionally `reason`.
     */
    public function process(array $message): array
    {
        // Step 1: Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $message)) {
                return $this->reject($message, "Missing required field: {$field}");
            }
            if ($message[$field] === '' || $message[$field] === null) {
                return $this->reject($message, "Required field is empty: {$field}");
            }
        }

        // Step 2: Validate amount — must parse as decimal and be strictly > 0
        $amountRaw = (string) $message['amount'];
        try {
            $isPositive = Money::isPositive($amountRaw);
        } catch (InvalidArgumentException) {
            return $this->reject($message, "Invalid amount: '{$amountRaw}' is not a valid decimal number");
        }

        if (!$isPositive) {
            return $this->reject($message, "Invalid amount: '{$amountRaw}' must be greater than zero");
        }

        // Step 3: Validate currency — must be a valid ISO 4217 code
        $currency = (string) $message['currency'];
        if (!Iso4217::isValid($currency)) {
            return $this->reject($message, "Invalid currency: '{$currency}' is not a recognised ISO 4217 code");
        }

        // All checks passed
        return $this->accept($message);
    }

    /**
     * Build a validated (accepted) result message and log the outcome.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function accept(array $message): array
    {
        $message['status'] = 'validated';

        $this->logger->log(
            step: self::STAGE_NAME,
            transactionId: (string) ($message['transaction_id'] ?? 'unknown'),
            outcome: 'validated',
            context: [
                'amount'   => $message['amount'] ?? null,
                'currency' => $message['currency'] ?? null,
            ],
        );

        return $message;
    }

    /**
     * Build a rejected result message and log the outcome.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function reject(array $message, string $reason): array
    {
        $message['status'] = 'rejected';
        $message['reason'] = $reason;

        $this->logger->log(
            step: self::STAGE_NAME,
            transactionId: (string) ($message['transaction_id'] ?? 'unknown'),
            outcome: 'rejected',
            context: ['reason' => $reason],
        );

        return $message;
    }

    /**
     * Delete a file from the processing directory.
     *
     * Uses the baseDir (injected at construction time) to form the path.
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
