<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Pipeline;

use BankingPipeline\Pipeline\Reporter;
use BankingPipeline\Shared\Envelope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Reporter::summarize().
 *
 * All tests use an isolated temp directory — the real shared/results/ directory
 * is NEVER touched. Summary files from a previous run are also tested to confirm
 * the reporter does not double-count its own output.
 *
 * Coverage targets:
 *   - Mixed run (settled + each rejection category) → correct counts + breakdown
 *   - Reconciliation invariant: settled + rejected === total
 *   - Zero-results run (empty dir) → all zeros, valid files written
 *   - All-rejected run → settled = 0
 *   - All-settled run → rejected = 0, empty breakdown
 *   - Re-summarise with pre-existing summary.json/summary.txt → files excluded
 *   - Malformed result file → skipped, recorded in errors, remaining counted
 *   - summary.json content is valid JSON with required keys
 *   - summary.txt contains headline counts
 */
final class ReporterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir()
            . '/reporter_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // =========================================================================
    // Helper: write a result envelope JSON file
    // =========================================================================

    /**
     * Write a settled result envelope to the temp results dir.
     */
    private function writeSettledResult(string $txnId): void
    {
        $envelope = Envelope::create(
            source: 'settlement',
            target: 'results',
            type: 'transaction',
            data: [
                'transaction_id' => $txnId,
                'status'         => 'settled',
                'amount'         => '1000.00',
                'currency'       => 'USD',
                'fee'            => '2.50',
                'net'            => '997.50',
            ],
        );
        file_put_contents(
            $this->tempDir . '/' . $txnId . '.json',
            $envelope->toJson(),
        );
    }

    /**
     * Write a rejected result envelope to the temp results dir.
     */
    private function writeRejectedResult(string $txnId, string $reason): void
    {
        $envelope = Envelope::create(
            source: 'validator',
            target: 'results',
            type: 'transaction',
            data: [
                'transaction_id' => $txnId,
                'status'         => 'rejected',
                'reason'         => $reason,
            ],
        );
        file_put_contents(
            $this->tempDir . '/' . $txnId . '.json',
            $envelope->toJson(),
        );
    }

    /**
     * Write raw JSON content to a file in the temp results dir (for malformed tests).
     */
    private function writeRawFile(string $filename, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $filename, $content);
    }

    // =========================================================================
    // Happy path: mixed run
    // =========================================================================

    #[Test]
    public function mixedRunProducesCorrectCountsAndBreakdown(): void
    {
        // 3 settled
        $this->writeSettledResult('TXN001');
        $this->writeSettledResult('TXN002');
        $this->writeSettledResult('TXN003');

        // 1 rejected per category
        $this->writeRejectedResult('TXN004', "Transaction is missing required field: 'currency'");
        $this->writeRejectedResult('TXN005', "Invalid amount: '-50.00' must be greater than zero");
        $this->writeRejectedResult('TXN006', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");
        $this->writeRejectedResult('TXN007', 'High-risk transaction: rules=[unusual_hour, cross_border], score=60');

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(7, $summary['total_processed']);
        self::assertSame(3, $summary['settled_count']);
        self::assertSame(4, $summary['rejected_count']);

        $bd = $summary['rejection_breakdown'];
        self::assertSame(1, $bd['missing-field']);
        self::assertSame(1, $bd['non-positive-amount']);
        self::assertSame(1, $bd['invalid-currency']);
        self::assertSame(1, $bd['high-risk']);
    }

    // =========================================================================
    // Reconciliation invariant
    // =========================================================================

    #[Test]
    public function settledPlusRejectedEqualsTotalProcessed(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeSettledResult('TXN002');
        $this->writeRejectedResult('TXN003', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(
            $summary['total_processed'],
            $summary['settled_count'] + $summary['rejected_count'],
        );
    }

    // =========================================================================
    // Zero results
    // =========================================================================

    #[Test]
    public function zeroResultsProducesAllZerosAndWritesFiles(): void
    {
        // No result files written — results dir is empty but exists
        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(0, $summary['total_processed']);
        self::assertSame(0, $summary['settled_count']);
        self::assertSame(0, $summary['rejected_count']);
        self::assertSame([], $summary['rejection_breakdown']);

        // Summary files must still be created
        self::assertFileExists($this->tempDir . '/summary.json');
        self::assertFileExists($this->tempDir . '/summary.txt');
    }

    #[Test]
    public function zeroResultsWhenDirectoryDoesNotExist(): void
    {
        // Point the reporter at a directory that does not yet exist
        $nonExistentDir = $this->tempDir . '/missing_results';
        $reporter = new Reporter($nonExistentDir);
        $summary  = $reporter->summarize();

        self::assertSame(0, $summary['total_processed']);
        self::assertSame(0, $summary['settled_count']);
        self::assertSame(0, $summary['rejected_count']);

        // The reporter should have created the dir and written the files
        self::assertFileExists($nonExistentDir . '/summary.json');
        self::assertFileExists($nonExistentDir . '/summary.txt');
    }

    // =========================================================================
    // All-rejected
    // =========================================================================

    #[Test]
    public function allRejectedRunHasZeroSettled(): void
    {
        $this->writeRejectedResult('TXN001', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");
        $this->writeRejectedResult('TXN002', "Invalid amount: '-100.00' must be greater than zero");
        $this->writeRejectedResult('TXN003', 'High-risk transaction: rules=[high_value], score=100');

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(3, $summary['total_processed']);
        self::assertSame(0, $summary['settled_count']);
        self::assertSame(3, $summary['rejected_count']);
        self::assertCount(3, $summary['rejection_breakdown']); // 3 categories
    }

    // =========================================================================
    // All-settled
    // =========================================================================

    #[Test]
    public function allSettledRunHasEmptyBreakdown(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeSettledResult('TXN002');

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(2, $summary['total_processed']);
        self::assertSame(2, $summary['settled_count']);
        self::assertSame(0, $summary['rejected_count']);
        self::assertSame([], $summary['rejection_breakdown']);
    }

    // =========================================================================
    // Summary-file exclusion (re-summarise after prior run)
    // =========================================================================

    #[Test]
    public function ownSummaryFilesAreNotCountedAsTransactions(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeRejectedResult('TXN002', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");

        // First summarize call writes summary.json and summary.txt
        $reporter = new Reporter($this->tempDir);
        $first    = $reporter->summarize();

        // Second call — summary files now exist in the dir
        $second = $reporter->summarize();

        // Counts must be identical; the summary files must not be counted
        self::assertSame($first['total_processed'], $second['total_processed']);
        self::assertSame($first['settled_count'], $second['settled_count']);
        self::assertSame($first['rejected_count'], $second['rejected_count']);
        self::assertSame(2, $second['total_processed']);
    }

    // =========================================================================
    // Malformed / unreadable result file
    // =========================================================================

    #[Test]
    public function malformedResultFileIsSkippedAndRecordedInErrors(): void
    {
        $this->writeSettledResult('TXN001');
        // Write an invalid JSON file
        $this->writeRawFile('MALFORMED.json', '{not valid json}}');

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        // The valid transaction is counted; the malformed one is not
        self::assertSame(1, $summary['total_processed']);
        self::assertSame(1, $summary['settled_count']);

        // An error entry must be recorded
        self::assertNotEmpty($summary['errors']);
        self::assertStringContainsString('MALFORMED.json', $summary['errors'][0]);
    }

    #[Test]
    public function emptyFileIsSkippedAndRecordedInErrors(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeRawFile('EMPTY.json', '');

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertSame(1, $summary['total_processed']);
        self::assertNotEmpty($summary['errors']);
    }

    // =========================================================================
    // summary.json — well-formed JSON with required keys
    // =========================================================================

    #[Test]
    public function summaryJsonHasRequiredKeys(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeRejectedResult('TXN002', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");

        $reporter = new Reporter($this->tempDir);
        $reporter->summarize();

        $jsonPath = $this->tempDir . '/summary.json';
        self::assertFileExists($jsonPath);

        $decoded = json_decode(file_get_contents($jsonPath), associative: true);
        self::assertIsArray($decoded);

        foreach (['total_processed', 'settled_count', 'rejected_count', 'rejection_breakdown', 'errors', 'generated_at'] as $key) {
            self::assertArrayHasKey($key, $decoded, "summary.json is missing key: {$key}");
        }

        self::assertSame(2, $decoded['total_processed']);
        self::assertSame(1, $decoded['settled_count']);
        self::assertSame(1, $decoded['rejected_count']);
        self::assertIsArray($decoded['rejection_breakdown']);
        self::assertIsArray($decoded['errors']);
        self::assertIsString($decoded['generated_at']);
    }

    // =========================================================================
    // summary.txt — contains headline counts
    // =========================================================================

    #[Test]
    public function summaryTxtContainsHeadlineCounts(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeRejectedResult('TXN002', "Invalid currency: 'XYZ' is not a recognised ISO 4217 code");

        $reporter = new Reporter($this->tempDir);
        $reporter->summarize();

        $txtPath = $this->tempDir . '/summary.txt';
        self::assertFileExists($txtPath);

        $txt = file_get_contents($txtPath);
        self::assertStringContainsString('2', $txt); // total_processed = 2
        self::assertStringContainsString('Settled', $txt);
        self::assertStringContainsString('Rejected', $txt);
        // Breakdown section should appear when there are rejections
        self::assertStringContainsString('invalid-currency', $txt);
    }

    #[Test]
    public function summaryTxtHasNoBreakdownSectionWhenAllSettled(): void
    {
        $this->writeSettledResult('TXN001');

        $reporter = new Reporter($this->tempDir);
        $reporter->summarize();

        $txt = file_get_contents($this->tempDir . '/summary.txt');
        // No rejection breakdown heading when there are no rejections
        self::assertStringNotContainsString('Rejection breakdown', $txt);
    }

    // =========================================================================
    // Reason categorisation (via DataProvider)
    // =========================================================================

    public static function reasonCategoryProvider(): array
    {
        return [
            'missing field — generic'         => ["Transaction is missing required field: 'amount'", 'missing-field'],
            'missing field — required field'  => ["Required field 'currency' is missing", 'missing-field'],
            'non-positive — greater than zero' => ["Invalid amount: '-100.00' must be greater than zero", 'non-positive-amount'],
            'non-positive — zero'              => ['Amount must be positive, got zero', 'non-positive-amount'],
            'invalid currency — currency word' => ["Invalid currency: 'XYZ' is not a recognised ISO 4217 code", 'invalid-currency'],
            'invalid currency — iso 4217'      => ['Currency code fails ISO 4217 check', 'invalid-currency'],
            'high risk — high-risk phrase'     => ['High-risk transaction: rules=[unusual_hour], score=60', 'high-risk'],
            'high risk — risk score phrase'    => ['Transaction rejected: risk score=70 exceeds threshold', 'high-risk'],
            'unknown reason'                   => ['Something completely unrecognised happened', 'unknown'],
        ];
    }

    #[DataProvider('reasonCategoryProvider')]
    #[Test]
    public function reasonIsNormalisedToExpectedCategory(string $rawReason, string $expectedCategory): void
    {
        $this->writeRejectedResult('TXN-CAT', $rawReason);

        $reporter = new Reporter($this->tempDir);
        $summary  = $reporter->summarize();

        self::assertArrayHasKey(
            $expectedCategory,
            $summary['rejection_breakdown'],
            "Expected category '{$expectedCategory}' not found in breakdown for reason: {$rawReason}",
        );
    }

    // =========================================================================
    // Return value matches written summary.json
    // =========================================================================

    #[Test]
    public function returnValueMatchesSummaryJsonContents(): void
    {
        $this->writeSettledResult('TXN001');
        $this->writeRejectedResult('TXN002', 'High-risk transaction: rules=[high_value], score=40');

        $reporter = new Reporter($this->tempDir);
        $returned = $reporter->summarize();

        $onDisk = json_decode(
            file_get_contents($this->tempDir . '/summary.json'),
            associative: true,
        );

        self::assertSame($returned['total_processed'], $onDisk['total_processed']);
        self::assertSame($returned['settled_count'], $onDisk['settled_count']);
        self::assertSame($returned['rejected_count'], $onDisk['rejected_count']);
        self::assertSame($returned['rejection_breakdown'], $onDisk['rejection_breakdown']);
    }

    // =========================================================================
    // Utility
    // =========================================================================

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }
        rmdir($path);
    }
}
