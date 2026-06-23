<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Pipeline;

use BankingPipeline\Pipeline\Integrator;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\FileQueue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour tests for the Integrator.
 *
 * All tests use isolated temp directories — the real shared/ directory is NEVER
 * touched. Every test injects a silent or capturing output sink so no pipeline
 * traces bleed through during `make test`.
 *
 * Coverage: happy path end-to-end, multiple rejection types, empty input,
 * malformed JSON record, dirty shared/ re-run, sink behaviour, exit/return codes.
 *
 * Note: The single full-pipeline integration test across the whole project lives in
 * tests/Pipeline/PipelineIntegrationTest.php (Task 9). This file owns the focused
 * Integrator unit/behaviour tests.
 */
final class IntegratorTest extends TestCase
{
    private string $tempDir;
    private string $sharedDir;
    private FileQueue $queue;

    /** Lines captured by the capturing sink. */
    private array $captured = [];

    protected function setUp(): void
    {
        // Each test gets its own isolated temp shared/ directory.
        $this->tempDir = sys_get_temp_dir() . '/integrator_test_' . bin2hex(random_bytes(8));
        $this->sharedDir = $this->tempDir . '/shared';
        mkdir($this->sharedDir, 0755, recursive: true);

        $this->queue = new FileQueue($this->sharedDir);
        $this->queue->initialize();

        $this->captured = [];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // -------------------------------------------------------------------------
    // Helper: capturing sink
    // -------------------------------------------------------------------------

    /**
     * Returns a callable sink that captures lines into $this->captured.
     */
    private function capturingSink(): callable
    {
        return function (string $line): void {
            $this->captured[] = $line;
        };
    }

    /**
     * Returns a no-op (silent) sink.
     */
    private function silentSink(): callable
    {
        return static function (string $line): void {};
    }

    /**
     * Returns a silent AuditLogger (no-op sink) so audit log lines don't bleed
     * through to STDERR during tests.
     */
    private function silentLogger(): AuditLogger
    {
        return new AuditLogger(sink: static function (string $line): void {});
    }

    // -------------------------------------------------------------------------
    // Helper: fixture JSON files
    // -------------------------------------------------------------------------

    /**
     * Write a JSON array of transaction records to a temp file and return its path.
     *
     * @param array $records
     */
    private function writeFixture(array $records): string
    {
        $path = $this->tempDir . '/transactions_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT));
        return $path;
    }

    /**
     * Write raw content to a temp file (for malformed-JSON tests).
     */
    private function writeRaw(string $content): string
    {
        $path = $this->tempDir . '/raw_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * A minimal valid transaction record that will be settled.
     */
    private function validTransaction(string $id = 'TXN001'): array
    {
        return [
            'transaction_id'      => $id,
            'timestamp'           => '2026-03-16T09:00:00Z',
            'source_account'      => 'ACC-1001',
            'destination_account' => 'ACC-2001',
            'amount'              => '1500.00',
            'currency'            => 'USD',
            'transaction_type'    => 'transfer',
            'metadata'            => ['country' => 'US'],
        ];
    }

    // -------------------------------------------------------------------------
    // Recursive temp-dir cleanup
    // -------------------------------------------------------------------------

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // =========================================================================
    // Tests
    // =========================================================================

    #[Test]
    public function happyPath_singleValidTransaction_producesOneSettledResult(): void
    {
        $fixture = $this->writeFixture([$this->validTransaction('TXN001')]);

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode, 'Expected exit code 0 for a successful run');

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(1, $resultFiles, 'Expected exactly one result file');

        // Read the result and verify it is settled
        $result = $this->queue->read($resultFiles[0], FileQueue::DIR_RESULTS);
        self::assertSame('settled', $result->data['status']);
        self::assertArrayHasKey('fee', $result->data);
        self::assertArrayHasKey('net', $result->data);
    }

    #[Test]
    public function happyPath_multipleValidTransactions_eachProducesExactlyOneResult(): void
    {
        $records = [
            $this->validTransaction('TXN001'),
            $this->validTransaction('TXN002'),
            $this->validTransaction('TXN003'),
        ];
        // Assign distinct amounts so the fee varies
        $records[1]['amount'] = '500.00';
        $records[2]['amount'] = '999.99';

        $fixture = $this->writeFixture($records);

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode);
        self::assertCount(3, $this->queue->listFiles(FileQueue::DIR_RESULTS));
    }

    #[Test]
    public function mixedTransactions_settledAndRejected_eachReachesResults(): void
    {
        // Record mix:
        //   TXN-A: valid, settled
        //   TXN-B: invalid currency (XYZ) → rejected by Validator
        //   TXN-C: negative amount → rejected by Validator
        //   TXN-D: high-value (25000) + US daytime → settled (score=40 < 60)
        //   TXN-E: high-risk (overnight + cross-border = 30+30=60) → rejected by FraudDetector
        $records = [
            [  // TXN-A: settled
                'transaction_id'      => 'TXN-A',
                'timestamp'           => '2026-03-16T10:00:00Z',
                'source_account'      => 'ACC-1',
                'destination_account' => 'ACC-2',
                'amount'              => '100.00',
                'currency'            => 'USD',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [  // TXN-B: rejected — bad currency
                'transaction_id'      => 'TXN-B',
                'timestamp'           => '2026-03-16T10:00:00Z',
                'source_account'      => 'ACC-1',
                'destination_account' => 'ACC-2',
                'amount'              => '200.00',
                'currency'            => 'XYZ',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [  // TXN-C: rejected — negative amount
                'transaction_id'      => 'TXN-C',
                'timestamp'           => '2026-03-16T10:00:00Z',
                'source_account'      => 'ACC-1',
                'destination_account' => 'ACC-2',
                'amount'              => '-100.00',
                'currency'            => 'USD',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [  // TXN-D: high value (>=10000) + US + daytime → score=40 → settled
                'transaction_id'      => 'TXN-D',
                'timestamp'           => '2026-03-16T10:00:00Z',
                'source_account'      => 'ACC-1',
                'destination_account' => 'ACC-2',
                'amount'              => '25000.00',
                'currency'            => 'USD',
                'transaction_type'    => 'wire_transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [  // TXN-E: overnight(02:00) + cross-border(DE) = 30+30=60 → rejected high-risk
                'transaction_id'      => 'TXN-E',
                'timestamp'           => '2026-03-16T02:47:00Z',
                'source_account'      => 'ACC-1',
                'destination_account' => 'ACC-2',
                'amount'              => '500.00',
                'currency'            => 'EUR',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'DE'],
            ],
        ];

        $fixture = $this->writeFixture($records);
        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode, 'All 5 transactions should reach results/');

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(5, $resultFiles, 'Expected one result per input transaction');

        // Read all results and bucket by status
        $settled  = 0;
        $rejected = 0;
        foreach ($resultFiles as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            match ($env->data['status']) {
                'settled'  => $settled++,
                'rejected' => $rejected++,
                default    => self::fail("Unexpected status: {$env->data['status']}"),
            };
        }

        // TXN-A, TXN-D → settled (2); TXN-B, TXN-C, TXN-E → rejected (3)
        self::assertSame(2, $settled,  'Expected 2 settled transactions');
        self::assertSame(3, $rejected, 'Expected 3 rejected transactions');
    }

    #[Test]
    public function settledTransaction_hasFeeAndNetFields(): void
    {
        $fixture = $this->writeFixture([$this->validTransaction('TXN001')]);

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $result = $this->queue->read($resultFiles[0], FileQueue::DIR_RESULTS);

        // Amount 1500.00 × 0.0025 = 3.75 fee, net = 1496.25
        self::assertSame('settled', $result->data['status']);
        self::assertSame('3.75', $result->data['fee']);
        self::assertSame('1496.25', $result->data['net']);
    }

    #[Test]
    public function validatorRejection_missingField_reachesResults(): void
    {
        $record = $this->validTransaction('TXN-MISSING');
        unset($record['currency']);  // Remove required field

        $fixture = $this->writeFixture([$record]);
        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode);

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(1, $resultFiles);

        $result = $this->queue->read($resultFiles[0], FileQueue::DIR_RESULTS);
        self::assertSame('rejected', $result->data['status']);
        self::assertStringContainsString('currency', $result->data['reason']);
    }

    #[Test]
    public function fraudDetectorRejection_highRisk_reachesResults(): void
    {
        // High value (>=10000) + unusual hour (02:xx) = 40+30=70 >= 60 → high-risk
        $record = $this->validTransaction('TXN-FRAUD');
        $record['amount']    = '15000.00';
        $record['timestamp'] = '2026-03-16T02:00:00Z';

        $fixture = $this->writeFixture([$record]);
        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode);

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(1, $resultFiles);

        $result = $this->queue->read($resultFiles[0], FileQueue::DIR_RESULTS);
        self::assertSame('rejected', $result->data['status']);
        self::assertStringContainsString('High-risk', $result->data['reason']);
    }

    // -------------------------------------------------------------------------
    // Sink behaviour
    // -------------------------------------------------------------------------

    #[Test]
    public function capturingSink_receivesProgressOutput(): void
    {
        $fixture = $this->writeFixture([$this->validTransaction('TXN001')]);

        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $integrator->run($fixture);

        $output = implode('', $this->captured);
        self::assertStringContainsString('Pipeline starting', $output);
        self::assertStringContainsString('Stage 1: Validator', $output);
        self::assertStringContainsString('Stage 2: Fraud Detector', $output);
        self::assertStringContainsString('Stage 3: Settlement', $output);
        self::assertStringContainsString('Pipeline complete', $output);
    }

    #[Test]
    public function silentSink_emitsNothingToStdout(): void
    {
        $fixture = $this->writeFixture([$this->validTransaction('TXN001')]);

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());

        // Capture STDOUT to verify nothing leaks through.
        ob_start();
        $integrator->run($fixture);
        $stdoutOutput = ob_get_clean();

        self::assertSame('', $stdoutOutput, 'Silent sink must not produce STDOUT output');
    }

    // -------------------------------------------------------------------------
    // Empty input
    // -------------------------------------------------------------------------

    #[Test]
    public function emptyInputFile_returnsZeroAndNoResults(): void
    {
        $fixture = $this->writeFixture([]);

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode, 'Empty input should return 0 (nothing to reconcile)');
        self::assertCount(0, $this->queue->listFiles(FileQueue::DIR_RESULTS));
    }

    #[Test]
    public function emptyInputFile_outputHasEarlyExitMessage(): void
    {
        $fixture = $this->writeFixture([]);

        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $integrator->run($fixture);

        $output = implode('', $this->captured);
        self::assertStringContainsString('No transactions to process', $output);
    }

    // -------------------------------------------------------------------------
    // Malformed JSON
    // -------------------------------------------------------------------------

    #[Test]
    public function malformedTopLevelJson_returnsFatalError(): void
    {
        $fixture = $this->writeRaw('{not valid json!!!');

        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(1, $exitCode, 'Malformed JSON must return exit code 1');

        $output = implode('', $this->captured);
        self::assertStringContainsString('ERROR', $output);
    }

    #[Test]
    public function malformedRecord_nonObjectElement_isSkippedAndReconciliationFails(): void
    {
        // One valid record and one malformed (a scalar instead of an object).
        // The scalar should be skipped; the valid record should still be processed.
        // Reconciliation sees 2 loaded (1 valid + 1 skipped = only 1 enqueued)
        // vs 1 result → the integrator should return 0 since 1 enqueued == 1 result.
        // Wait — skipped records are NOT enqueued, so inputCount = 1 and resultCount = 1.
        // The malformed element is skipped with a WARNING.

        $records = [
            $this->validTransaction('TXN001'),
            'this_is_a_scalar_not_an_object', // malformed element
        ];

        // Write manually because json_encode handles mixed arrays
        $fixture = $this->writeRaw(json_encode($records));

        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        // inputCount = 1 (only the valid record was enqueued), resultCount = 1
        self::assertSame(0, $exitCode, 'Skipped malformed record should not cause failure when counts match');

        $output = implode('', $this->captured);
        self::assertStringContainsString('WARNING', $output, 'Should log a warning about the skipped record');

        self::assertCount(1, $this->queue->listFiles(FileQueue::DIR_RESULTS));
    }

    #[Test]
    public function malformedRecord_allSkipped_reconciliationPassesAtZero(): void
    {
        // Array of non-objects only — all skipped, inputCount=0, resultCount=0 → pass
        $fixture = $this->writeRaw(json_encode(['string1', 'string2', 42]));

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        // inputCount=0, resultCount=0 → reconciliation passes
        self::assertSame(0, $exitCode);
        self::assertCount(0, $this->queue->listFiles(FileQueue::DIR_RESULTS));
    }

    // -------------------------------------------------------------------------
    // Dirty shared/ — prior run state is cleared
    // -------------------------------------------------------------------------

    #[Test]
    public function dirtyShared_clearsPriorRunState(): void
    {
        // First run
        $fixture1 = $this->writeFixture([$this->validTransaction('TXN001')]);
        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture1);

        $resultsAfterFirstRun = count($this->queue->listFiles(FileQueue::DIR_RESULTS));
        self::assertSame(1, $resultsAfterFirstRun, 'First run should produce 1 result');

        // Second run with a different input (different transaction IDs don't collide
        // because the Integrator clears results/ before each run)
        $fixture2 = $this->writeFixture([
            $this->validTransaction('TXN002'),
            $this->validTransaction('TXN003'),
        ]);
        $integrator2 = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator2->run($fixture2);

        self::assertSame(0, $exitCode);
        $resultsAfterSecondRun = count($this->queue->listFiles(FileQueue::DIR_RESULTS));
        self::assertSame(2, $resultsAfterSecondRun, 'Second run should produce 2 results (prior run cleared)');
    }

    #[Test]
    public function dirtyShared_priorResultsAreCleared_notAccumulated(): void
    {
        // First run leaves a result
        $fixture1 = $this->writeFixture([$this->validTransaction('TXN-FIRST')]);
        $integrator1 = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator1->run($fixture1);

        // Second run: same transaction ID — should replace, not add
        // (FileQueue.write() overwrites by transaction_id filename)
        $fixture2 = $this->writeFixture([$this->validTransaction('TXN-SECOND')]);
        $integrator2 = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator2->run($fixture2);

        self::assertSame(0, $exitCode);
        // Results dir must have exactly 1 file (prior TXN-FIRST was cleared)
        self::assertCount(
            1,
            $this->queue->listFiles(FileQueue::DIR_RESULTS),
            'Prior run results must be cleared before a new run'
        );
    }

    // -------------------------------------------------------------------------
    // Missing input file
    // -------------------------------------------------------------------------

    #[Test]
    public function missingInputFile_returnsNonZeroExitCode(): void
    {
        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $exitCode = $integrator->run('/nonexistent/path/transactions.json');

        self::assertSame(1, $exitCode);

        $output = implode('', $this->captured);
        self::assertStringContainsString('ERROR', $output);
    }

    // -------------------------------------------------------------------------
    // Full sample-transactions.json fixture (trimmed, representative)
    // -------------------------------------------------------------------------

    #[Test]
    public function sampleTransactionsFixture_eightTransactions_eachReachesResults(): void
    {
        // Mirror of sample-transactions.json (all 8 records)
        $records = [
            [  // TXN001: settled
                'transaction_id' => 'TXN001', 'timestamp' => '2026-03-16T09:00:00Z',
                'source_account' => 'ACC-1001', 'destination_account' => 'ACC-2001',
                'amount' => '1500.00', 'currency' => 'USD', 'transaction_type' => 'transfer',
                'description' => 'Monthly rent payment', 'metadata' => ['channel' => 'online', 'country' => 'US'],
            ],
            [  // TXN002: high value (25000>=10000) + US + daytime → score=40 → settled
                'transaction_id' => 'TXN002', 'timestamp' => '2026-03-16T09:15:00Z',
                'source_account' => 'ACC-1002', 'destination_account' => 'ACC-3001',
                'amount' => '25000.00', 'currency' => 'USD', 'transaction_type' => 'wire_transfer',
                'description' => 'Equipment purchase', 'metadata' => ['channel' => 'branch', 'country' => 'US'],
            ],
            [  // TXN003: 9999.99 < 10000, US, daytime → score=0 → settled
                'transaction_id' => 'TXN003', 'timestamp' => '2026-03-16T09:30:00Z',
                'source_account' => 'ACC-1003', 'destination_account' => 'ACC-9999',
                'amount' => '9999.99', 'currency' => 'USD', 'transaction_type' => 'transfer',
                'description' => 'Consulting payment', 'metadata' => ['channel' => 'online', 'country' => 'US'],
            ],
            [  // TXN004: overnight(02:47)+cross-border(DE) = 30+30=60 → rejected high-risk
                'transaction_id' => 'TXN004', 'timestamp' => '2026-03-16T02:47:00Z',
                'source_account' => 'ACC-1004', 'destination_account' => 'ACC-5500',
                'amount' => '500.00', 'currency' => 'EUR', 'transaction_type' => 'transfer',
                'description' => 'Invoice #4471', 'metadata' => ['channel' => 'api', 'country' => 'DE'],
            ],
            [  // TXN005: high value (75000) + US + daytime → score=40 → settled
                'transaction_id' => 'TXN005', 'timestamp' => '2026-03-16T10:00:00Z',
                'source_account' => 'ACC-1005', 'destination_account' => 'ACC-6600',
                'amount' => '75000.00', 'currency' => 'USD', 'transaction_type' => 'wire_transfer',
                'description' => 'Property settlement', 'metadata' => ['channel' => 'branch', 'country' => 'US'],
            ],
            [  // TXN006: rejected — invalid currency XYZ
                'transaction_id' => 'TXN006', 'timestamp' => '2026-03-16T10:05:00Z',
                'source_account' => 'ACC-1006', 'destination_account' => 'ACC-7700',
                'amount' => '200.00', 'currency' => 'XYZ', 'transaction_type' => 'transfer',
                'description' => 'Test payment', 'metadata' => ['channel' => 'online', 'country' => 'US'],
            ],
            [  // TXN007: rejected — negative amount
                'transaction_id' => 'TXN007', 'timestamp' => '2026-03-16T10:10:00Z',
                'source_account' => 'ACC-1007', 'destination_account' => 'ACC-8800',
                'amount' => '-100.00', 'currency' => 'GBP', 'transaction_type' => 'refund',
                'description' => 'Refund for order #8821', 'metadata' => ['channel' => 'online', 'country' => 'GB'],
            ],
            [  // TXN008: USD, US, daytime, 3200 < 10000 → score=0 → settled
                'transaction_id' => 'TXN008', 'timestamp' => '2026-03-16T10:15:00Z',
                'source_account' => 'ACC-1008', 'destination_account' => 'ACC-9900',
                'amount' => '3200.00', 'currency' => 'USD', 'transaction_type' => 'transfer',
                'description' => 'Salary advance', 'metadata' => ['channel' => 'mobile', 'country' => 'US'],
            ],
        ];

        $fixture = $this->writeFixture($records);
        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        self::assertSame(0, $exitCode);

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(8, $resultFiles, 'All 8 transactions must reach results/');

        // Bucket by status
        $settled  = 0;
        $rejected = 0;
        foreach ($resultFiles as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if ($env->data['status'] === 'settled') {
                $settled++;
            } elseif ($env->data['status'] === 'rejected') {
                $rejected++;
            }
        }

        // TXN001,002,003,005,008 → 5 settled; TXN004,006,007 → 3 rejected
        self::assertSame(5, $settled,  'Expected 5 settled transactions from the sample fixture');
        self::assertSame(3, $rejected, 'Expected 3 rejected transactions from the sample fixture');
    }

    #[Test]
    public function topLevelJsonObject_notArray_returnsFatalError(): void
    {
        // json_decode on a JSON object with associative:true returns a PHP associative
        // array. An associative array IS a PHP array, so `is_array()` returns true and
        // the integrator iterates over the values. When values are scalars they are
        // skipped (WARNING), and inputCount=0, resultCount=0 → reconciliation passes.
        // To trigger the non-array branch, we need a JSON scalar at the top level
        // (e.g. a plain string, number, or boolean).
        $fixture = $this->writeRaw('"this is a plain JSON string, not an array"');

        $integrator = new Integrator($this->sharedDir, $this->capturingSink(), $this->silentLogger());
        $exitCode = $integrator->run($fixture);

        // Top-level scalar decoded as string — is_array() returns false → returns 1
        self::assertSame(1, $exitCode);

        $output = implode('', $this->captured);
        self::assertStringContainsString('ERROR', $output);
    }
}
