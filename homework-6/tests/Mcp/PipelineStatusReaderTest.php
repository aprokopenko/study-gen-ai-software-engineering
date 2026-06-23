<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Mcp;

use BankingPipeline\Mcp\PipelineStatusReader;
use BankingPipeline\Shared\Envelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PipelineStatusReader.
 *
 * All tests use an isolated temp directory.  The real shared/results/ directory
 * is NEVER touched.
 *
 * Coverage targets:
 *   - get_transaction_status: settled (fee/net present)
 *   - get_transaction_status: rejected (reason present)
 *   - get_transaction_status: unknown transaction_id → "not found", no crash
 *   - get_transaction_status: empty transaction_id → clean error response
 *   - list_pipeline_results: mixed results → correct count and entries
 *   - list_pipeline_results: empty results dir → count 0, empty array
 *   - getPipelineSummary: reads summary.txt when present
 *   - getPipelineSummary: falls back to summary.json when summary.txt absent
 *   - getPipelineSummary: returns placeholder when no summary files exist
 *   - readAllResultEnvelopes: skips malformed JSON files silently
 *   - readAllResultEnvelopes: skips summary.json / summary.txt entries
 *   - No results directory at all → graceful empty results
 */
final class PipelineStatusReaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir()
            . '/mcp_reader_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function writeResultFile(string $filename, array $data): void
    {
        $envelope = Envelope::create(
            source: 'settlement',
            target: 'results',
            type: 'transaction',
            data: $data,
        );
        file_put_contents(
            $this->tempDir . DIRECTORY_SEPARATOR . $filename,
            $envelope->toJson(),
        );
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (glob($path . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($path);
    }

    private function makeReader(): PipelineStatusReader
    {
        return new PipelineStatusReader($this->tempDir);
    }

    // =========================================================================
    // get_transaction_status — settled
    // =========================================================================

    #[Test]
    public function getTransactionStatusReturnsSettledWithFeeAndNet(): void
    {
        $this->writeResultFile('txn001.json', [
            'transaction_id' => 'TXN001',
            'status'         => 'settled',
            'amount'         => '1500.00',
            'currency'       => 'USD',
            'fee'            => '3.75',
            'net'            => '1496.25',
        ]);

        $result = $this->makeReader()->getTransactionStatus('TXN001');

        $this->assertTrue($result['found']);
        $this->assertSame('TXN001', $result['transaction_id']);
        $this->assertSame('settled', $result['status']);
        $this->assertSame('1500.00', $result['amount']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame('3.75', $result['fee']);
        $this->assertSame('1496.25', $result['net']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    // =========================================================================
    // get_transaction_status — rejected
    // =========================================================================

    #[Test]
    public function getTransactionStatusReturnsRejectedWithReason(): void
    {
        $this->writeResultFile('txn002.json', [
            'transaction_id' => 'TXN002',
            'status'         => 'rejected',
            'reason'         => 'High-risk transaction: risk score 70 >= cutoff 60 (high-value)',
        ]);

        $result = $this->makeReader()->getTransactionStatus('TXN002');

        $this->assertTrue($result['found']);
        $this->assertSame('TXN002', $result['transaction_id']);
        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('high-risk', strtolower((string) $result['reason']));
        $this->assertArrayNotHasKey('fee', $result);
        $this->assertArrayNotHasKey('net', $result);
    }

    // =========================================================================
    // get_transaction_status — unknown transaction_id
    // =========================================================================

    #[Test]
    public function getTransactionStatusReturnsNotFoundForUnknownId(): void
    {
        // Write one result file but look up a different ID
        $this->writeResultFile('txn001.json', [
            'transaction_id' => 'TXN001',
            'status'         => 'settled',
            'amount'         => '100.00',
            'currency'       => 'USD',
            'fee'            => '0.25',
            'net'            => '99.75',
        ]);

        $result = $this->makeReader()->getTransactionStatus('TXN_NONEXISTENT');

        $this->assertFalse($result['found']);
        $this->assertSame('TXN_NONEXISTENT', $result['transaction_id']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('TXN_NONEXISTENT', (string) $result['message']);
    }

    // =========================================================================
    // get_transaction_status — empty transaction_id
    // =========================================================================

    #[Test]
    public function getTransactionStatusReturnsErrorForEmptyId(): void
    {
        $result = $this->makeReader()->getTransactionStatus('');

        $this->assertFalse($result['found']);
        $this->assertArrayHasKey('message', $result);
    }

    // =========================================================================
    // list_pipeline_results — mixed results
    // =========================================================================

    #[Test]
    public function listPipelineResultsReturnsMixedResults(): void
    {
        $this->writeResultFile('txn001.json', [
            'transaction_id' => 'TXN001',
            'status'         => 'settled',
            'amount'         => '500.00',
            'currency'       => 'USD',
            'fee'            => '1.25',
            'net'            => '498.75',
        ]);
        $this->writeResultFile('txn002.json', [
            'transaction_id' => 'TXN002',
            'status'         => 'rejected',
            'reason'         => 'Invalid currency code: XYZ',
        ]);
        $this->writeResultFile('txn003.json', [
            'transaction_id' => 'TXN003',
            'status'         => 'rejected',
            'reason'         => 'Amount must be greater than zero',
        ]);

        $result = $this->makeReader()->listPipelineResults();

        $this->assertSame(3, $result['count']);
        $this->assertCount(3, $result['transactions']);

        $ids = array_column($result['transactions'], 'transaction_id');
        $this->assertContains('TXN001', $ids);
        $this->assertContains('TXN002', $ids);
        $this->assertContains('TXN003', $ids);

        // Find TXN001 entry and check status
        $txn001 = array_values(array_filter(
            $result['transactions'],
            static fn(array $t) => $t['transaction_id'] === 'TXN001',
        ))[0];
        $this->assertSame('settled', $txn001['status']);
    }

    // =========================================================================
    // list_pipeline_results — empty results directory
    // =========================================================================

    #[Test]
    public function listPipelineResultsReturnsEmptyForEmptyDir(): void
    {
        $result = $this->makeReader()->listPipelineResults();

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['transactions']);
    }

    // =========================================================================
    // list_pipeline_results — no results directory at all
    // =========================================================================

    #[Test]
    public function listPipelineResultsReturnsEmptyWhenDirAbsent(): void
    {
        $reader = new PipelineStatusReader('/tmp/no_such_dir_' . bin2hex(random_bytes(8)));

        $result = $reader->listPipelineResults();

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['transactions']);
    }

    // =========================================================================
    // getPipelineSummary — reads summary.txt
    // =========================================================================

    #[Test]
    public function getPipelineSummaryReturnsSummaryTxt(): void
    {
        $expectedText = "=== Banking Pipeline Run Summary ===\nTotal processed : 3\nSettled         : 2\n";
        file_put_contents($this->tempDir . '/summary.txt', $expectedText);

        $result = $this->makeReader()->getPipelineSummary();

        $this->assertSame($expectedText, $result);
    }

    // =========================================================================
    // getPipelineSummary — falls back to summary.json
    // =========================================================================

    #[Test]
    public function getPipelineSummaryFallsBackToSummaryJson(): void
    {
        $summaryData = [
            'total_processed'    => 2,
            'settled_count'      => 1,
            'rejected_count'     => 1,
            'rejection_breakdown' => ['high-risk' => 1],
            'errors'             => [],
            'generated_at'       => '2026-06-23T12:00:00+00:00',
        ];
        file_put_contents(
            $this->tempDir . '/summary.json',
            json_encode($summaryData, JSON_PRETTY_PRINT),
        );

        $result = $this->makeReader()->getPipelineSummary();

        // Result should be valid JSON containing our data
        $decoded = json_decode($result, associative: true);
        $this->assertIsArray($decoded);
        $this->assertSame(2, $decoded['total_processed']);
        $this->assertSame(1, $decoded['settled_count']);
    }

    // =========================================================================
    // getPipelineSummary — placeholder when no summary files exist
    // =========================================================================

    #[Test]
    public function getPipelineSummaryReturnsPlaceholderWhenNoSummaryExists(): void
    {
        $result = $this->makeReader()->getPipelineSummary();

        $this->assertStringContainsString('No pipeline run summary', $result);
    }

    // =========================================================================
    // getPipelineSummary — placeholder when directory does not exist
    // =========================================================================

    #[Test]
    public function getPipelineSummaryReturnsPlaceholderWhenDirAbsent(): void
    {
        $reader = new PipelineStatusReader('/tmp/no_such_dir_' . bin2hex(random_bytes(8)));

        $result = $reader->getPipelineSummary();

        $this->assertStringContainsString('No pipeline run summary', $result);
    }

    // =========================================================================
    // Skips malformed JSON files silently
    // =========================================================================

    #[Test]
    public function listPipelineResultsSkipsMalformedFiles(): void
    {
        file_put_contents($this->tempDir . '/bad.json', 'NOT VALID JSON!!!');
        $this->writeResultFile('good.json', [
            'transaction_id' => 'TXN_GOOD',
            'status'         => 'settled',
            'amount'         => '100.00',
            'currency'       => 'USD',
            'fee'            => '0.25',
            'net'            => '99.75',
        ]);

        $result = $this->makeReader()->listPipelineResults();

        // Only the valid envelope is returned; bad.json is silently skipped.
        $this->assertSame(1, $result['count']);
        $this->assertSame('TXN_GOOD', $result['transactions'][0]['transaction_id']);
    }

    // =========================================================================
    // Summary files (summary.json / summary.txt) are excluded from transaction list
    // =========================================================================

    #[Test]
    public function listPipelineResultsExcludesSummaryFiles(): void
    {
        // Write a summary.json that looks like an envelope (it is not — Reporter writes
        // raw summary data, not an envelope). Confirm the reader skips it.
        file_put_contents($this->tempDir . '/summary.json', json_encode([
            'total_processed' => 5,
            'settled_count'   => 3,
            'rejected_count'  => 2,
        ]));

        $this->writeResultFile('txn001.json', [
            'transaction_id' => 'TXN001',
            'status'         => 'settled',
            'amount'         => '100.00',
            'currency'       => 'USD',
            'fee'            => '0.25',
            'net'            => '99.75',
        ]);

        $result = $this->makeReader()->listPipelineResults();

        // summary.json is excluded; only txn001.json counts
        $this->assertSame(1, $result['count']);
        $this->assertSame('TXN001', $result['transactions'][0]['transaction_id']);
    }

    // =========================================================================
    // get_transaction_status — no results directory
    // =========================================================================

    #[Test]
    public function getTransactionStatusReturnsNotFoundWhenDirAbsent(): void
    {
        $reader = new PipelineStatusReader('/tmp/no_such_dir_' . bin2hex(random_bytes(8)));

        $result = $reader->getTransactionStatus('TXN001');

        $this->assertFalse($result['found']);
        $this->assertSame('TXN001', $result['transaction_id']);
    }
}
