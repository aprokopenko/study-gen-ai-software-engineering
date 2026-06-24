<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Pipeline;

use BankingPipeline\Pipeline\Integrator;
use BankingPipeline\Pipeline\Reporter;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\FileQueue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Full-pipeline integration test.
 *
 * Runs the Integrator end-to-end on a purpose-built fixture that exercises
 * all four rejection paths (missing field, non-positive amount, invalid currency,
 * high-risk fraud) PLUS the happy-path settlement with exact fee/net math.
 *
 * Isolation:
 *   - Each test works in its own temp directory — the real shared/ directory is
 *     NEVER touched.
 *   - All output (progress traces + audit logs) flows through silent sinks so
 *     nothing bleeds into `make test` output.
 *
 * Fixture design (6 transactions):
 *   INT001 — valid, US, daytime, 1500.00 → settled (fee 3.75, net 1496.25)
 *   INT002 — missing required field (destination_account) → rejected (missing-field)
 *   INT003 — non-positive amount (-50.00) → rejected (non-positive-amount)
 *   INT004 — invalid ISO 4217 currency (XYZ) → rejected (invalid-currency)
 *   INT005 — high-risk: overnight (02:00) + cross-border (DE) = 30+30=60 → rejected (high-risk)
 *   INT006 — valid, US, daytime, 500.00 → settled (fee 1.25, net 498.75)
 *
 * This gives: 6 inputs → 6 results; 2 settled, 4 rejected (one of each rejection type).
 */
final class PipelineIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $sharedDir;
    private FileQueue $queue;

    protected function setUp(): void
    {
        $this->tempDir  = sys_get_temp_dir() . '/pipeline_integration_' . bin2hex(random_bytes(8));
        $this->sharedDir = $this->tempDir . '/shared';
        mkdir($this->sharedDir, 0755, recursive: true);

        $this->queue = new FileQueue($this->sharedDir);
        $this->queue->initialize();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Silent callable — discards all output so no pipeline traces bleed through.
     */
    private function silentSink(): callable
    {
        return static function (string $line): void {};
    }

    /**
     * Silent AuditLogger so no audit lines reach STDERR during tests.
     */
    private function silentLogger(): AuditLogger
    {
        return new AuditLogger(sink: static function (string $line): void {});
    }

    /**
     * Write a JSON fixture file and return its path.
     */
    private function writeFixture(array $records): string
    {
        $path = $this->tempDir . '/fixture_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $path;
    }

    /**
     * Build the integration test fixture (6 transactions, all four rejection paths).
     *
     * INT001 — happy path: USD, US, daytime, 1500.00 → settled
     * INT002 — missing required field (destination_account) → rejected
     * INT003 — non-positive amount (-50.00) → rejected
     * INT004 — invalid currency XYZ → rejected
     * INT005 — high-risk: overnight (02:00) + cross-border (DE), score=60 → rejected
     * INT006 — happy path: USD, US, daytime, 500.00 → settled
     */
    private function buildFixture(): array
    {
        return [
            [   // INT001: settled — fee/net math asserted explicitly
                'transaction_id'      => 'INT001',
                'timestamp'           => '2026-03-16T10:00:00Z',
                'source_account'      => 'ACC-INT-1',
                'destination_account' => 'ACC-INT-2',
                'amount'              => '1500.00',
                'currency'            => 'USD',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [   // INT002: rejected — missing required field (destination_account)
                'transaction_id'      => 'INT002',
                'timestamp'           => '2026-03-16T10:01:00Z',
                'source_account'      => 'ACC-INT-3',
                // destination_account intentionally omitted
                'amount'              => '200.00',
                'currency'            => 'USD',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [   // INT003: rejected — non-positive amount
                'transaction_id'      => 'INT003',
                'timestamp'           => '2026-03-16T10:02:00Z',
                'source_account'      => 'ACC-INT-5',
                'destination_account' => 'ACC-INT-6',
                'amount'              => '-50.00',
                'currency'            => 'USD',
                'transaction_type'    => 'refund',
                'metadata'            => ['country' => 'US'],
            ],
            [   // INT004: rejected — invalid ISO 4217 currency code
                'transaction_id'      => 'INT004',
                'timestamp'           => '2026-03-16T10:03:00Z',
                'source_account'      => 'ACC-INT-7',
                'destination_account' => 'ACC-INT-8',
                'amount'              => '300.00',
                'currency'            => 'XYZ',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
            [   // INT005: rejected — high-risk (overnight 02:00 + cross-border DE = 30+30=60 >= cutoff)
                'transaction_id'      => 'INT005',
                'timestamp'           => '2026-03-16T02:00:00Z',
                'source_account'      => 'ACC-INT-9',
                'destination_account' => 'ACC-INT-10',
                'amount'              => '400.00',
                'currency'            => 'EUR',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'DE'],
            ],
            [   // INT006: settled — second happy path, explicit fee/net check
                'transaction_id'      => 'INT006',
                'timestamp'           => '2026-03-16T11:00:00Z',
                'source_account'      => 'ACC-INT-11',
                'destination_account' => 'ACC-INT-12',
                'amount'              => '500.00',
                'currency'            => 'USD',
                'transaction_type'    => 'transfer',
                'metadata'            => ['country' => 'US'],
            ],
        ];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // =========================================================================
    // Full-pipeline integration tests
    // =========================================================================

    /**
     * Core reconciliation: every input transaction produces exactly one result file.
     */
    #[Test]
    public function fullPipeline_sixTransactions_eachProducesExactlyOneResult(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode   = $integrator->run($fixture);

        self::assertSame(0, $exitCode, 'Integrator must return 0 when all transactions reach results/');

        $resultFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        self::assertCount(6, $resultFiles, 'Exactly one result file per input transaction (reconciliation invariant)');
    }

    /**
     * Happy path — INT001 (1500.00 USD): fee = 0.25% = 3.75, net = 1496.25.
     * These are the exact string values required by the spec.
     */
    #[Test]
    public function happyPath_1500UsdTransaction_exactFeeAndNet(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        // Find INT001's result file
        $int001Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT001') {
                $int001Result = $env;
                break;
            }
        }

        self::assertNotNull($int001Result, 'INT001 result file must exist');
        self::assertSame('settled', $int001Result->data['status'], 'INT001 must be settled');
        // 1500.00 × 0.0025 = 3.75 (exact half-up decimal, no float rounding error)
        self::assertSame('3.75',    $int001Result->data['fee'],    'Fee for 1500.00 must be exactly 3.75');
        self::assertSame('1496.25', $int001Result->data['net'],    'Net for 1500.00 must be exactly 1496.25');
    }

    /**
     * Happy path — INT006 (500.00 USD): fee = 1.25, net = 498.75.
     */
    #[Test]
    public function happyPath_500UsdTransaction_exactFeeAndNet(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $int006Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT006') {
                $int006Result = $env;
                break;
            }
        }

        self::assertNotNull($int006Result, 'INT006 result file must exist');
        self::assertSame('settled', $int006Result->data['status']);
        // 500.00 × 0.0025 = 1.25
        self::assertSame('1.25',   $int006Result->data['fee']);
        self::assertSame('498.75', $int006Result->data['net']);
    }

    /**
     * Rejection path — missing field (INT002): destination_account is absent.
     * The validator must catch this and write a rejected result with a reason
     * that mentions the missing field.
     */
    #[Test]
    public function rejectionPath_missingField_producesRejectedResult(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $int002Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT002') {
                $int002Result = $env;
                break;
            }
        }

        self::assertNotNull($int002Result, 'INT002 result file must exist');
        self::assertSame('rejected', $int002Result->data['status'], 'INT002 must be rejected (missing field)');
        self::assertArrayHasKey('reason', $int002Result->data, 'Rejection must include a reason');
        // The reason must mention "destination_account" or "missing"
        $reason = strtolower($int002Result->data['reason']);
        self::assertTrue(
            str_contains($reason, 'destination_account') || str_contains($reason, 'missing'),
            "Rejection reason must describe the missing field; got: {$int002Result->data['reason']}"
        );
    }

    /**
     * Rejection path — non-positive amount (INT003): amount = -50.00.
     * The validator must reject this with a reason mentioning the amount.
     */
    #[Test]
    public function rejectionPath_nonPositiveAmount_producesRejectedResult(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $int003Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT003') {
                $int003Result = $env;
                break;
            }
        }

        self::assertNotNull($int003Result, 'INT003 result file must exist');
        self::assertSame('rejected', $int003Result->data['status'], 'INT003 must be rejected (non-positive amount)');
        self::assertArrayHasKey('reason', $int003Result->data);
        $reason = strtolower($int003Result->data['reason']);
        self::assertTrue(
            str_contains($reason, 'amount'),
            "Rejection reason must mention 'amount'; got: {$int003Result->data['reason']}"
        );
    }

    /**
     * Rejection path — invalid currency (INT004): currency = XYZ.
     * The validator must reject this with a reason mentioning the currency.
     */
    #[Test]
    public function rejectionPath_invalidCurrency_producesRejectedResult(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $int004Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT004') {
                $int004Result = $env;
                break;
            }
        }

        self::assertNotNull($int004Result, 'INT004 result file must exist');
        self::assertSame('rejected', $int004Result->data['status'], 'INT004 must be rejected (invalid currency)');
        self::assertArrayHasKey('reason', $int004Result->data);
        $reason = strtolower($int004Result->data['reason']);
        self::assertTrue(
            str_contains($reason, 'currency') || str_contains($reason, 'xyz'),
            "Rejection reason must mention 'currency' or 'XYZ'; got: {$int004Result->data['reason']}"
        );
    }

    /**
     * Rejection path — high-risk fraud (INT005): overnight (02:00) + cross-border (DE).
     * Score = 30 (unusual hour) + 30 (cross-border) = 60 >= cutoff.
     * The fraud detector must reject this with a reason mentioning risk.
     */
    #[Test]
    public function rejectionPath_highRiskFraud_producesRejectedResult(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $int005Result = null;
        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['transaction_id'] ?? '') === 'INT005') {
                $int005Result = $env;
                break;
            }
        }

        self::assertNotNull($int005Result, 'INT005 result file must exist');
        self::assertSame('rejected', $int005Result->data['status'], 'INT005 must be rejected (high-risk)');
        self::assertArrayHasKey('reason', $int005Result->data);
        $reason = strtolower($int005Result->data['reason']);
        self::assertTrue(
            str_contains($reason, 'high-risk') ||
            str_contains($reason, 'high risk') ||
            str_contains($reason, 'risk'),
            "Rejection reason must mention 'risk'; got: {$int005Result->data['reason']}"
        );
    }

    /**
     * Overall counts: 6 inputs → 2 settled + 4 rejected.
     */
    #[Test]
    public function fullPipeline_sixTransactions_correctSettledAndRejectedCounts(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $exitCode   = $integrator->run($fixture);

        self::assertSame(0, $exitCode);

        $settled  = 0;
        $rejected = 0;

        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            match ($env->data['status'] ?? '') {
                'settled'  => $settled++,
                'rejected' => $rejected++,
                default    => null,
            };
        }

        self::assertSame(2, $settled,  '2 of the 6 fixture transactions should be settled');
        self::assertSame(4, $rejected, '4 of the 6 fixture transactions should be rejected');
    }

    /**
     * Reporter reconciliation: after the run, the Reporter's summary counts
     * must match the result files — settled + rejected === total_processed.
     */
    #[Test]
    public function reporterSummary_afterRun_countsReconcileWithResults(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $resultsDir = $this->sharedDir . DIRECTORY_SEPARATOR . FileQueue::DIR_RESULTS;
        $reporter   = new Reporter($resultsDir);
        $summary    = $reporter->summarize();

        // settled + rejected === total_processed
        self::assertSame(
            $summary['total_processed'],
            $summary['settled_count'] + $summary['rejected_count'],
            'Reporter totals must satisfy: total_processed = settled + rejected'
        );

        self::assertSame(6, $summary['total_processed'], 'Reporter must count all 6 transactions');
        self::assertSame(2, $summary['settled_count'],   'Reporter must report 2 settled');
        self::assertSame(4, $summary['rejected_count'],  'Reporter must report 4 rejected');

        // All four rejection categories must appear in the breakdown
        $breakdown = $summary['rejection_breakdown'];

        self::assertArrayHasKey('missing-field',       $breakdown, 'Breakdown must include missing-field');
        self::assertArrayHasKey('non-positive-amount', $breakdown, 'Breakdown must include non-positive-amount');
        self::assertArrayHasKey('invalid-currency',    $breakdown, 'Breakdown must include invalid-currency');
        self::assertArrayHasKey('high-risk',           $breakdown, 'Breakdown must include high-risk');

        // Each category should have exactly 1 rejection
        self::assertSame(1, $breakdown['missing-field'],       'missing-field count must be 1');
        self::assertSame(1, $breakdown['non-positive-amount'], 'non-positive-amount count must be 1');
        self::assertSame(1, $breakdown['invalid-currency'],    'invalid-currency count must be 1');
        self::assertSame(1, $breakdown['high-risk'],           'high-risk count must be 1');
    }

    /**
     * All four rejection reasons appear in result files with their 'reason' field.
     * This is a consolidated end-to-end assertion across all rejection types.
     */
    #[Test]
    public function allFourRejectionPaths_reasonFieldPresentInEveryRejection(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());
        $integrator->run($fixture);

        $rejectedWithReason = 0;

        foreach ($this->queue->listFiles(FileQueue::DIR_RESULTS) as $filename) {
            $env = $this->queue->read($filename, FileQueue::DIR_RESULTS);
            if (($env->data['status'] ?? '') === 'rejected') {
                self::assertArrayHasKey(
                    'reason',
                    $env->data,
                    "Rejected result {$filename} must have a 'reason' field"
                );
                self::assertNotEmpty(
                    $env->data['reason'],
                    "Rejected result {$filename} must have a non-empty reason"
                );
                $rejectedWithReason++;
            }
        }

        self::assertSame(4, $rejectedWithReason, 'All 4 rejected transactions must have a reason field');
    }

    /**
     * No pipeline or application output must leak to STDOUT during the test run.
     * The silent sink and silent logger must suppress all output completely.
     */
    #[Test]
    public function silentSinks_noOutputLeaksToStdout(): void
    {
        $fixture = $this->writeFixture($this->buildFixture());

        $integrator = new Integrator($this->sharedDir, $this->silentSink(), $this->silentLogger());

        ob_start();
        $integrator->run($fixture);
        $output = ob_get_clean();

        self::assertSame('', $output, 'No pipeline output must leak to STDOUT during tests');
    }
}
