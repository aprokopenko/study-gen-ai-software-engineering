<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Stages;

use BankingPipeline\Config\SettlementConfig;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Stages\Settlement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Settlement stage.
 *
 * All tests use a temporary directory and a silent/capturing audit sink.
 * The real shared/ directory is never touched.
 *
 * Covered:
 *   - Happy path (known amount → expected fee/net as strings)
 *   - Very large amounts (no precision loss)
 *   - Fee rounding boundaries (half-up at the currency minor unit)
 *   - Small amounts where fee rounds to 0.00 or 0.01
 *   - Reconciliation: fee + net == amount for standard 2-decimal inputs
 *   - All monetary output values are PHP strings
 *   - status=settled on every settled message
 *   - Audit logging (outcome, no PII leakage)
 *   - run() queue orchestration (output→processing→results, status=settled)
 */
final class SettlementTest extends TestCase
{
    private string $tempDir;
    private FileQueue $queue;
    private array $logCapture;
    private AuditLogger $logger;
    private Settlement $settlement;

    protected function setUp(): void
    {
        // Isolated temp directory — never touches the real shared/
        $this->tempDir = sys_get_temp_dir() . '/settlement_test_' . bin2hex(random_bytes(8));
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

        $this->settlement = new Settlement(
            queue: $this->queue,
            logger: $this->logger,
            baseDir: $this->tempDir,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    // =========================================================================
    // process() — happy path
    // =========================================================================

    #[Test]
    public function processSettlesANormalTransaction(): void
    {
        $message = $this->fraudCheckedMessage(['amount' => '1500.00']);

        $result = $this->settlement->process($message);

        $this->assertSame('settled', $result['status']);
    }

    #[Test]
    public function processComputesCorrectFeeForTypicalAmount(): void
    {
        // 1500.00 × 0.0025 = 3.75 (exact, no rounding needed)
        $message = $this->fraudCheckedMessage(['amount' => '1500.00']);

        $result = $this->settlement->process($message);

        $this->assertSame('3.75', $result['fee']);
    }

    #[Test]
    public function processComputesCorrectNetForTypicalAmount(): void
    {
        // 1500.00 − 3.75 = 1496.25
        $message = $this->fraudCheckedMessage(['amount' => '1500.00']);

        $result = $this->settlement->process($message);

        $this->assertSame('1496.25', $result['net']);
    }

    #[Test]
    public function processFeeAndNetAreBothStrings(): void
    {
        $message = $this->fraudCheckedMessage(['amount' => '1500.00']);

        $result = $this->settlement->process($message);

        $this->assertIsString($result['fee'], 'fee must be a string');
        $this->assertIsString($result['net'], 'net must be a string');
    }

    #[Test]
    public function processPreservesOriginalFields(): void
    {
        $message = $this->fraudCheckedMessage();

        $result = $this->settlement->process($message);

        foreach (['transaction_id', 'amount', 'currency', 'source_account', 'destination_account'] as $field) {
            $this->assertArrayHasKey($field, $result, "Expected field '{$field}' to be preserved");
        }
    }

    // =========================================================================
    // process() — fee and net as strings (data-provider approach)
    // =========================================================================

    /**
     * @return array<string, array{string, string, string}>
     *   [description => [amount, expected_fee, expected_net]]
     */
    public static function amountFeeNetProvider(): array
    {
        return [
            // Standard cases
            'typical_1500.00'        => ['1500.00',    '3.75',      '1496.25'],
            'round_100.00'           => ['100.00',     '0.25',      '99.75'],
            'round_200.00'           => ['200.00',     '0.50',      '199.50'],
            'round_1000.00'          => ['1000.00',    '2.50',      '997.50'],

            // Rounding boundary: 0.25% ends in .5 at the 3rd decimal place
            // 2.00 × 0.0025 = 0.005 → half-up → 0.01; net = 1.99
            'rounding_boundary_2.00' => ['2.00',       '0.01',      '1.99'],

            // Rounding boundary: 0.25% ends in .xx5 → rounds up
            // 202.00 × 0.0025 = 0.505 → half-up → 0.51; net = 201.49
            'rounding_boundary_202.00' => ['202.00',   '0.51',      '201.49'],

            // Small amounts where fee rounds to 0.00
            // 0.40 × 0.0025 = 0.001 → rounds to 0.00; net = 0.40
            'tiny_rounds_to_zero_fee'  => ['0.40',     '0.00',      '0.40'],

            // Small amounts where fee rounds to 0.01
            // 4.00 × 0.0025 = 0.01 → exact; net = 3.99
            'tiny_rounds_to_one_cent'  => ['4.00',     '0.01',      '3.99'],
        ];
    }

    #[Test]
    #[DataProvider('amountFeeNetProvider')]
    public function processFeeAndNetMatchExpectedValues(
        string $amount,
        string $expectedFee,
        string $expectedNet,
    ): void {
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        $this->assertSame($expectedFee, $result['fee'], "Fee mismatch for amount={$amount}");
        $this->assertSame($expectedNet, $result['net'], "Net mismatch for amount={$amount}");
    }

    // =========================================================================
    // process() — very large amounts (no precision loss)
    // =========================================================================

    #[Test]
    public function processHandlesVeryLargeAmountWithoutPrecisionLoss(): void
    {
        // 9999999.99 × 0.0025 = 24999.99975 → half-up → 25000.00
        // net = 9999999.99 − 25000.00 = 9974999.99
        $message = $this->fraudCheckedMessage(['amount' => '9999999.99']);

        $result = $this->settlement->process($message);

        $this->assertSame('25000.00', $result['fee']);
        $this->assertSame('9974999.99', $result['net']);
        $this->assertIsString($result['fee']);
        $this->assertIsString($result['net']);
    }

    #[Test]
    public function processHandlesExtremelyLargeAmount(): void
    {
        // 99999999999.00 × 0.0025 = 249999999.9975 → half-up → 250000000.00
        // net = 99999999999.00 − 250000000.00 = 99749999999.00
        $message = $this->fraudCheckedMessage(['amount' => '99999999999.00']);

        $result = $this->settlement->process($message);

        $this->assertSame('250000000.00', $result['fee']);
        $this->assertSame('99749999999.00', $result['net']);
    }

    // =========================================================================
    // process() — fee rounding: half-up at the third decimal
    // =========================================================================

    #[Test]
    public function processFeeRoundsHalfUpWhenThirdDecimalIsFive(): void
    {
        // 2.00 × 0.0025 = 0.005 → 3rd decimal is 5 → half-up → 0.01 (not 0.00)
        $message = $this->fraudCheckedMessage(['amount' => '2.00']);

        $result = $this->settlement->process($message);

        // Must round UP (half-up), not DOWN
        $this->assertSame('0.01', $result['fee']);
    }

    #[Test]
    public function processFeeRoundsHalfUpWhenRawFeeEndsInXX5(): void
    {
        // 202.00 × 0.0025 = 0.505 → 3rd decimal is 5 → half-up → 0.51
        $message = $this->fraudCheckedMessage(['amount' => '202.00']);

        $result = $this->settlement->process($message);

        $this->assertSame('0.51', $result['fee']);
    }

    #[Test]
    public function processFeeDoesNotRoundUpWhenThirdDecimalIsFour(): void
    {
        // 1.60 × 0.0025 = 0.004 → 3rd decimal is 4 → rounds DOWN → 0.00
        $message = $this->fraudCheckedMessage(['amount' => '1.60']);

        $result = $this->settlement->process($message);

        $this->assertSame('0.00', $result['fee']);
    }

    // =========================================================================
    // process() — reconciliation: fee + net == amount
    // =========================================================================

    #[Test]
    public function processFeeAndNetReconcileToAmountWhenBothExact(): void
    {
        // 1500.00 × 0.0025 = 3.75 (exact) → no rounding occurs
        // fee + net must equal amount exactly
        $amount = '1500.00';
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        // Use bcadd for string decimal addition to avoid float
        $sum = bcadd($result['fee'], $result['net'], 2);
        $this->assertSame($amount, $sum, 'fee + net must equal amount');
    }

    #[Test]
    public function processFeeAndNetReconcileWhenFeeIsExactCents(): void
    {
        // 100.00 × 0.0025 = 0.25 (exact)
        $amount = '100.00';
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        $sum = bcadd($result['fee'], $result['net'], 2);
        $this->assertSame($amount, $sum);
    }

    #[Test]
    public function processFeeAndNetReconcileForBoundaryRoundingCase(): void
    {
        // 2.00 × 0.0025 = 0.005 → fee rounds to 0.01
        // net = 2.00 − 0.01 = 1.99; fee + net = 0.01 + 1.99 = 2.00 ✓
        $amount = '2.00';
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        $sum = bcadd($result['fee'], $result['net'], 2);
        $this->assertSame($amount, $sum, 'fee + net must equal amount even after rounding');
    }

    #[Test]
    public function processFeeAndNetReconcileForSecondBoundaryCase(): void
    {
        // 202.00 × 0.0025 = 0.505 → fee rounds to 0.51
        // net = 202.00 − 0.51 = 201.49; fee + net = 0.51 + 201.49 = 202.00 ✓
        $amount = '202.00';
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        $sum = bcadd($result['fee'], $result['net'], 2);
        $this->assertSame($amount, $sum);
    }

    #[Test]
    public function processFeeAndNetReconcileForZeroFeeCase(): void
    {
        // 0.40 × 0.0025 = 0.001 → fee rounds to 0.00
        // net = 0.40 − 0.00 = 0.40; fee + net = 0.40 ✓
        $amount = '0.40';
        $message = $this->fraudCheckedMessage(['amount' => $amount]);

        $result = $this->settlement->process($message);

        $sum = bcadd($result['fee'], $result['net'], 2);
        $this->assertSame($amount, $sum);
    }

    // =========================================================================
    // process() — audit logging and PII
    // =========================================================================

    #[Test]
    public function processLogsSettledOutcome(): void
    {
        $message = $this->fraudCheckedMessage(['transaction_id' => 'TXN-SETTLE-01']);

        $this->settlement->process($message);

        $this->assertCount(1, $this->logCapture);
        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame('settlement', $entry['step']);
        $this->assertSame('TXN-SETTLE-01', $entry['transaction_id']);
        $this->assertSame('settled', $entry['outcome']);
    }

    #[Test]
    public function processDoesNotLogSourceAccountPii(): void
    {
        $message = $this->fraudCheckedMessage(['source_account' => 'ACC-PII-SENSITIVE-0042']);

        $this->settlement->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-PII-SENSITIVE-0042',
            $logLine,
            'source_account PII must not appear in the audit log'
        );
    }

    #[Test]
    public function processDoesNotLogDestinationAccountPii(): void
    {
        $message = $this->fraudCheckedMessage(['destination_account' => 'ACC-DEST-SECRET-7777']);

        $this->settlement->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-DEST-SECRET-7777',
            $logLine,
            'destination_account PII must not appear in the audit log'
        );
    }

    #[Test]
    public function processLogsAmountFeeAndNet(): void
    {
        $message = $this->fraudCheckedMessage(['amount' => '1000.00']);

        $this->settlement->process($message);

        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame('1000.00', $entry['context']['amount']);
        $this->assertSame('2.50', $entry['context']['fee']);
        $this->assertSame('997.50', $entry['context']['net']);
    }

    // =========================================================================
    // run() — queue orchestration
    // =========================================================================

    #[Test]
    public function runSettlesAndWritesToResults(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['amount' => '500.00']));

        $counts = $this->settlement->run();

        $this->assertSame(1, $counts['settled']);

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $this->assertCount(1, $resultsFiles);
    }

    #[Test]
    public function runResultEnvelopeHasCorrectStatus(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['amount' => '500.00']));

        $this->settlement->run();

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $resultEnvelope = $this->queue->read($resultsFiles[0], FileQueue::DIR_RESULTS);

        $this->assertSame('settled', $resultEnvelope->data['status']);
    }

    #[Test]
    public function runResultEnvelopeContainsFeeAndNet(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['amount' => '500.00']));

        $this->settlement->run();

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $resultEnvelope = $this->queue->read($resultsFiles[0], FileQueue::DIR_RESULTS);

        $this->assertArrayHasKey('fee', $resultEnvelope->data);
        $this->assertArrayHasKey('net', $resultEnvelope->data);
        $this->assertSame('1.25', $resultEnvelope->data['fee']);   // 500 × 0.0025
        $this->assertSame('498.75', $resultEnvelope->data['net']); // 500 − 1.25
    }

    #[Test]
    public function runResultEnvelopeHasCorrectSourceAndTarget(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage());

        $this->settlement->run();

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $resultEnvelope = $this->queue->read($resultsFiles[0], FileQueue::DIR_RESULTS);

        $this->assertSame('settlement', $resultEnvelope->source);
        $this->assertSame('results', $resultEnvelope->target);
    }

    #[Test]
    public function runClearsProcessingAfterCompletion(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage());

        $this->settlement->run();

        $processingFiles = $this->queue->listFiles(FileQueue::DIR_PROCESSING);
        $this->assertCount(0, $processingFiles, 'Processing directory should be empty after run');
    }

    #[Test]
    public function runClearsOutputAfterProcessing(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage());

        $this->settlement->run();

        $outputFiles = $this->queue->listFiles(FileQueue::DIR_OUTPUT);
        $this->assertCount(0, $outputFiles, 'Output directory should be empty after run');
    }

    #[Test]
    public function runHandlesMultipleMessages(): void
    {
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['transaction_id' => 'TXN001', 'amount' => '100.00']));
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['transaction_id' => 'TXN002', 'amount' => '200.00']));
        $this->dropEnvelopeInOutput($this->fraudCheckedMessage(['transaction_id' => 'TXN003', 'amount' => '300.00']));

        $counts = $this->settlement->run();

        $this->assertSame(3, $counts['settled']);

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $this->assertCount(3, $resultsFiles);
    }

    #[Test]
    public function runReturnsZeroCountWhenOutputIsEmpty(): void
    {
        $counts = $this->settlement->run();

        $this->assertSame(0, $counts['settled']);
    }

    // =========================================================================
    // SettlementConfig — constant verification (light config tests)
    // =========================================================================

    #[Test]
    public function feeRateConstantIsDefinedAndCorrect(): void
    {
        $this->assertSame('0.0025', SettlementConfig::FEE_RATE, 'FEE_RATE must be 0.0025 (0.25%)');
    }

    #[Test]
    public function feeRateDescriptionMatchesActualRate(): void
    {
        $this->assertSame('0.25%', SettlementConfig::FEE_RATE_DESCRIPTION);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a fraud-checked transaction message ready for settlement.
     *
     * Defaults are set so no fraud rules fire and the message is "clean".
     * Override any field via $overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fraudCheckedMessage(array $overrides = []): array
    {
        $base = [
            'transaction_id'      => 'TXN001',
            'timestamp'           => '2026-03-16T09:00:00Z',
            'source_account'      => 'ACC-1001',
            'destination_account' => 'ACC-2001',
            'amount'              => '1500.00',
            'currency'            => 'USD',
            'transaction_type'    => 'transfer',
            'status'              => 'fraud_checked',
            'risk_score'          => 0,
            'risk_reasons'        => [],
            'metadata'            => ['country' => 'US'],
        ];

        foreach ($overrides as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Wrap a fraud-checked message in an envelope and drop it into shared/output
     * (the hand-off directory from the FraudDetector, which Settlement reads).
     *
     * @param array<string, mixed> $data
     */
    private function dropEnvelopeInOutput(array $data): void
    {
        $envelope = Envelope::create(
            source: 'fraud_detector',
            target: 'settlement',
            type: 'transaction',
            data: $data,
        );
        $this->queue->write(FileQueue::DIR_OUTPUT, $envelope);
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
