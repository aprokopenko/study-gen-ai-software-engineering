<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Stages;

use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Stages\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Validator stage.
 *
 * All tests use a temporary directory and a silent/capturing audit sink.
 * The real shared/ directory is never touched.
 */
final class ValidatorTest extends TestCase
{
    private string $tempDir;
    private FileQueue $queue;
    private array $logCapture;
    private AuditLogger $logger;
    private Validator $validator;

    protected function setUp(): void
    {
        // Isolated temp directory — never touches the real shared/
        $this->tempDir = sys_get_temp_dir() . '/validator_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, recursive: true);

        $this->queue = new FileQueue($this->tempDir);
        $this->queue->initialize();

        // Capturing sink — silent, stores all log lines for assertion
        $this->logCapture = [];
        $this->logger = new AuditLogger(
            sink: function (string $line): void {
                $this->logCapture[] = $line;
            }
        );

        $this->validator = new Validator(
            queue: $this->queue,
            logger: $this->logger,
            baseDir: $this->tempDir,
        );
    }

    protected function tearDown(): void
    {
        // Clean up the temp directory recursively
        $this->removeDirectory($this->tempDir);
    }

    // =========================================================================
    // process() — happy path
    // =========================================================================

    #[Test]
    public function processAcceptsFullyValidTransaction(): void
    {
        $message = $this->validMessage();

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    #[Test]
    public function processPreservesAllOriginalFieldsWhenValid(): void
    {
        $message = $this->validMessage();

        $result = $this->validator->process($message);

        // All original fields must be present in the result
        foreach ($message as $key => $value) {
            $this->assertArrayHasKey($key, $result, "Expected field '{$key}' to be preserved");
            $this->assertSame($value, $result[$key], "Field '{$key}' value was altered");
        }
    }

    // =========================================================================
    // process() — missing required fields
    // =========================================================================

    #[Test]
    #[DataProvider('requiredFieldsProvider')]
    public function processRejectsWhenRequiredFieldIsMissing(string $fieldToRemove): void
    {
        $message = $this->validMessage();
        unset($message[$fieldToRemove]);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString($fieldToRemove, $result['reason']);
        $this->assertStringContainsString('Missing required field', $result['reason']);
    }

    public static function requiredFieldsProvider(): array
    {
        return [
            'transaction_id'       => ['transaction_id'],
            'timestamp'            => ['timestamp'],
            'source_account'       => ['source_account'],
            'destination_account'  => ['destination_account'],
            'amount'               => ['amount'],
            'currency'             => ['currency'],
            'transaction_type'     => ['transaction_type'],
        ];
    }

    // =========================================================================
    // process() — empty required fields
    // =========================================================================

    #[Test]
    #[DataProvider('requiredFieldsProvider')]
    public function processRejectsWhenRequiredFieldIsEmpty(string $fieldToEmpty): void
    {
        $message = $this->validMessage();
        $message[$fieldToEmpty] = '';

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString($fieldToEmpty, $result['reason']);
    }

    #[Test]
    #[DataProvider('requiredFieldsProvider')]
    public function processRejectsWhenRequiredFieldIsNull(string $fieldToNull): void
    {
        $message = $this->validMessage();
        $message[$fieldToNull] = null;

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertArrayHasKey('reason', $result);
    }

    // =========================================================================
    // process() — amount validation edge cases
    // =========================================================================

    #[Test]
    public function processRejectsNegativeAmount(): void
    {
        $message = $this->validMessage(['amount' => '-100.00']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('-100.00', $result['reason']);
        $this->assertStringContainsString('greater than zero', $result['reason']);
    }

    #[Test]
    public function processRejectsZeroAmount(): void
    {
        $message = $this->validMessage(['amount' => '0']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('greater than zero', $result['reason']);
    }

    #[Test]
    public function processRejectsZeroDecimalAmount(): void
    {
        $message = $this->validMessage(['amount' => '0.00']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('greater than zero', $result['reason']);
    }

    #[Test]
    public function processRejectsNonNumericAmount(): void
    {
        $message = $this->validMessage(['amount' => 'abc']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('abc', $result['reason']);
        $this->assertStringContainsString('valid decimal', $result['reason']);
    }

    #[Test]
    public function processRejectsAmountWithLettersSuffix(): void
    {
        $message = $this->validMessage(['amount' => '100abc']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('valid decimal', $result['reason']);
    }

    #[Test]
    public function processAcceptsPositiveDecimalAmount(): void
    {
        $message = $this->validMessage(['amount' => '0.01']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    #[Test]
    public function processAcceptsLargeAmount(): void
    {
        $message = $this->validMessage(['amount' => '9999999999.99']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    // =========================================================================
    // process() — currency validation edge cases
    // =========================================================================

    #[Test]
    public function processRejectsUnknownCurrencyCode(): void
    {
        $message = $this->validMessage(['currency' => 'XYZ']);

        $result = $this->validator->process($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('XYZ', $result['reason']);
        $this->assertStringContainsString('ISO 4217', $result['reason']);
    }

    #[Test]
    public function processRejectsLowercaseCurrencyCode(): void
    {
        $message = $this->validMessage(['currency' => 'usd']);

        $result = $this->validator->process($message);

        // ISO 4217 codes are uppercase; lowercase is not a valid match
        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('usd', $result['reason']);
    }

    #[Test]
    public function processAcceptsUsdCurrency(): void
    {
        $message = $this->validMessage(['currency' => 'USD']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    #[Test]
    public function processAcceptsEurCurrency(): void
    {
        $message = $this->validMessage(['currency' => 'EUR']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    #[Test]
    public function processAcceptsGbpCurrency(): void
    {
        $message = $this->validMessage(['currency' => 'GBP']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    #[Test]
    public function processAcceptsJpyCurrency(): void
    {
        $message = $this->validMessage(['currency' => 'JPY']);

        $result = $this->validator->process($message);

        $this->assertSame('validated', $result['status']);
    }

    // =========================================================================
    // process() — rejection reason is meaningful
    // =========================================================================

    #[Test]
    public function rejectionReasonDescribesTheProblem(): void
    {
        $message = $this->validMessage(['amount' => '-50.00']);

        $result = $this->validator->process($message);

        // Reason must be a non-empty, human-readable string explaining the issue
        $this->assertNotEmpty($result['reason']);
        $this->assertGreaterThan(10, strlen($result['reason']), 'Reason should be descriptive');
    }

    // =========================================================================
    // process() — audit logging
    // =========================================================================

    #[Test]
    public function processLogsValidatedOutcome(): void
    {
        $message = $this->validMessage();

        $this->validator->process($message);

        $this->assertCount(1, $this->logCapture);
        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame('validator', $entry['step']);
        $this->assertSame('TXN001', $entry['transaction_id']);
        $this->assertSame('validated', $entry['outcome']);
    }

    #[Test]
    public function processLogsRejectedOutcome(): void
    {
        $message = $this->validMessage(['currency' => 'XYZ']);

        $this->validator->process($message);

        $this->assertCount(1, $this->logCapture);
        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame('validator', $entry['step']);
        $this->assertSame('rejected', $entry['outcome']);
    }

    #[Test]
    public function processDoesNotLogPiiSourceAccount(): void
    {
        $message = $this->validMessage([
            'source_account' => 'ACC-SENSITIVE-1001',
        ]);

        $this->validator->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-SENSITIVE-1001',
            $logLine,
            'source_account PII must not appear in the audit log'
        );
    }

    #[Test]
    public function processDoesNotLogPiiDestinationAccount(): void
    {
        $message = $this->validMessage([
            'destination_account' => 'ACC-SENSITIVE-9999',
        ]);

        $this->validator->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-SENSITIVE-9999',
            $logLine,
            'destination_account PII must not appear in the audit log'
        );
    }

    // =========================================================================
    // run() — file queue integration
    // =========================================================================

    #[Test]
    public function runProcessesValidTransactionToOutput(): void
    {
        $this->dropEnvelopeInInput($this->validMessage());

        $counts = $this->validator->run();

        $this->assertSame(1, $counts['validated']);
        $this->assertSame(0, $counts['rejected']);

        // Result envelope written to output/
        $outputFiles = $this->queue->listFiles(FileQueue::DIR_OUTPUT);
        $this->assertCount(1, $outputFiles);

        $resultEnvelope = $this->queue->read($outputFiles[0], FileQueue::DIR_OUTPUT);
        $this->assertSame('validated', $resultEnvelope->data['status']);
        $this->assertSame('validator', $resultEnvelope->source);
        $this->assertSame('fraud_detector', $resultEnvelope->target);
    }

    #[Test]
    public function runProcessesRejectedTransactionToResults(): void
    {
        $this->dropEnvelopeInInput($this->validMessage(['currency' => 'XYZ']));

        $counts = $this->validator->run();

        $this->assertSame(0, $counts['validated']);
        $this->assertSame(1, $counts['rejected']);

        // Result envelope written to results/
        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $this->assertCount(1, $resultsFiles);

        $resultEnvelope = $this->queue->read($resultsFiles[0], FileQueue::DIR_RESULTS);
        $this->assertSame('rejected', $resultEnvelope->data['status']);
        $this->assertArrayHasKey('reason', $resultEnvelope->data);
    }

    #[Test]
    public function runClearsProcessingAfterCompletion(): void
    {
        $this->dropEnvelopeInInput($this->validMessage());

        $this->validator->run();

        // Processing directory should be empty after run
        $processingFiles = $this->queue->listFiles(FileQueue::DIR_PROCESSING);
        $this->assertCount(0, $processingFiles, 'Processing directory should be empty after run');
    }

    #[Test]
    public function runHandlesMultipleFilesInInput(): void
    {
        // Drop 3 valid + 1 invalid message
        $this->dropEnvelopeInInput($this->validMessage(['transaction_id' => 'TXN001']));
        $this->dropEnvelopeInInput($this->validMessage(['transaction_id' => 'TXN002']));
        $this->dropEnvelopeInInput($this->validMessage(['transaction_id' => 'TXN003']));
        $this->dropEnvelopeInInput($this->validMessage([
            'transaction_id' => 'TXN004',
            'currency'       => 'XYZ',
        ]));

        $counts = $this->validator->run();

        $this->assertSame(3, $counts['validated']);
        $this->assertSame(1, $counts['rejected']);
    }

    #[Test]
    public function runReturnsZeroCountsWhenInputIsEmpty(): void
    {
        $counts = $this->validator->run();

        $this->assertSame(0, $counts['validated']);
        $this->assertSame(0, $counts['rejected']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a fully valid transaction message, with optional field overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validMessage(array $overrides = []): array
    {
        return array_merge([
            'transaction_id'      => 'TXN001',
            'timestamp'           => '2026-03-16T09:00:00Z',
            'source_account'      => 'ACC-1001',
            'destination_account' => 'ACC-2001',
            'amount'              => '1500.00',
            'currency'            => 'USD',
            'transaction_type'    => 'transfer',
        ], $overrides);
    }

    /**
     * Wrap a message in an envelope and drop it into shared/input.
     *
     * @param array<string, mixed> $data
     */
    private function dropEnvelopeInInput(array $data): void
    {
        $envelope = Envelope::create(
            source: 'integrator',
            target: 'validator',
            type: 'transaction',
            data: $data,
        );
        $this->queue->write(FileQueue::DIR_INPUT, $envelope);
    }

    /**
     * Recursively remove a directory and its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                $this->removeDirectory($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($dir);
    }
}
