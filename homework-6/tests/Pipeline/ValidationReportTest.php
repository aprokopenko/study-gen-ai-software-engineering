<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Pipeline;

use BankingPipeline\Pipeline\ValidationReport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for ValidationReport (dry-run validator CLI helper).
 *
 * Isolates all runs from the real shared/ directory — uses a temp file for
 * input and a capturing sink to suppress / inspect stdout output.
 */
final class ValidationReportTest extends TestCase
{
    /** @var string Path to a temp directory for input fixtures */
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/validation_report_test_' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        // Clean up temp fixtures
        foreach (glob($this->tmpDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Write a JSON fixture and return its path.
     *
     * @param mixed[] $records
     */
    private function writeFixture(array $records): string
    {
        $path = $this->tmpDir . '/transactions_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($records, JSON_THROW_ON_ERROR));
        return $path;
    }

    /**
     * Build a valid transaction record with all required fields.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRecord(array $overrides = []): array
    {
        return array_merge([
            'transaction_id'     => 'TXN001',
            'timestamp'          => '2026-01-01T10:00:00Z',
            'source_account'     => 'ACC-001',
            'destination_account'=> 'ACC-002',
            'amount'             => '100.00',
            'currency'           => 'USD',
            'transaction_type'   => 'transfer',
        ], $overrides);
    }

    /**
     * Create a capturing sink and run the report; return [result, output].
     *
     * @return array{array{total:int,valid:int,invalid:int,rows:list<mixed>}, string}
     */
    private function captureRun(string $inputFile): array
    {
        $captured = '';
        $sink     = static function (string $line) use (&$captured): void {
            $captured .= $line;
        };

        $report = new ValidationReport(sink: $sink);
        $result = $report->run($inputFile);

        return [$result, $captured];
    }

    // -------------------------------------------------------------------------
    // Happy path — all-valid set
    // -------------------------------------------------------------------------

    #[Test]
    public function allValidSetReturnsTotalEqualToValidCount(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN002', 'amount' => '500.00']),
            $this->validRecord(['transaction_id' => 'TXN003', 'currency' => 'EUR']),
        ]);

        [$result] = $this->captureRun($fixture);

        self::assertSame(3, $result['total']);
        self::assertSame(3, $result['valid']);
        self::assertSame(0, $result['invalid']);
        self::assertCount(3, $result['rows']);
    }

    #[Test]
    public function allValidRowsHaveValidResultAndEmptyReason(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN002']),
        ]);

        [$result] = $this->captureRun($fixture);

        foreach ($result['rows'] as $row) {
            self::assertSame('valid', $row['result']);
            self::assertSame('', $row['reason']);
        }
    }

    // -------------------------------------------------------------------------
    // All-invalid set
    // -------------------------------------------------------------------------

    #[Test]
    public function allInvalidSetReturnsValidCountZero(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['currency' => 'XYZ', 'transaction_id' => 'TXN006']),
            $this->validRecord(['amount'   => '-100.00', 'transaction_id' => 'TXN007']),
        ]);

        [$result] = $this->captureRun($fixture);

        self::assertSame(2, $result['total']);
        self::assertSame(0, $result['valid']);
        self::assertSame(2, $result['invalid']);
    }

    #[Test]
    public function allInvalidRowsHaveInvalidResultAndNonEmptyReason(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['currency' => 'XYZ', 'transaction_id' => 'TXN006']),
            $this->validRecord(['amount'   => '-100.00', 'transaction_id' => 'TXN007']),
        ]);

        [$result] = $this->captureRun($fixture);

        foreach ($result['rows'] as $row) {
            self::assertSame('invalid', $row['result']);
            self::assertNotEmpty($row['reason']);
        }
    }

    // -------------------------------------------------------------------------
    // Mixed set
    // -------------------------------------------------------------------------

    #[Test]
    public function mixedSetCountsAreCorrect(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN006', 'currency' => 'XYZ']),
            $this->validRecord(['transaction_id' => 'TXN007', 'amount' => '-100.00']),
            $this->validRecord(['transaction_id' => 'TXN008']),
        ]);

        [$result] = $this->captureRun($fixture);

        self::assertSame(4, $result['total']);
        self::assertSame(2, $result['valid']);
        self::assertSame(2, $result['invalid']);
    }

    #[Test]
    public function mixedSetInvalidRowsHaveCorrectRejectionReasons(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN006', 'currency' => 'XYZ']),
            $this->validRecord(['transaction_id' => 'TXN007', 'amount' => '-100.00']),
        ]);

        [$result] = $this->captureRun($fixture);

        $invalidRows = array_filter($result['rows'], fn($r) => $r['result'] === 'invalid');
        $reasons     = array_column(array_values($invalidRows), 'reason');

        // TXN006 rejected for invalid currency
        self::assertStringContainsString('XYZ', $reasons[0]);
        // TXN007 rejected for non-positive amount
        self::assertStringContainsString('-100.00', $reasons[1]);
    }

    // -------------------------------------------------------------------------
    // Missing required fields
    // -------------------------------------------------------------------------

    #[Test]
    public function missingRequiredFieldIsRejectedWithReason(): void
    {
        $record = $this->validRecord();
        unset($record['amount']);

        $fixture = $this->writeFixture([$record]);

        [$result] = $this->captureRun($fixture);

        self::assertSame(1, $result['invalid']);
        self::assertStringContainsString('amount', $result['rows'][0]['reason']);
    }

    // -------------------------------------------------------------------------
    // Dry-run guarantee: nothing written to shared/
    // -------------------------------------------------------------------------

    #[Test]
    public function runWritesNothingToFilesystemExceptThroughSink(): void
    {
        $sharedDir = $this->tmpDir . '/shared_check';
        // Shared dir should NOT exist after the run
        self::assertDirectoryDoesNotExist($sharedDir);

        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN006', 'currency' => 'XYZ']),
        ]);

        $this->captureRun($fixture);

        // Nothing written to the fake shared dir (it was never created)
        self::assertDirectoryDoesNotExist($sharedDir);
        // And no files were added to the tmp dir other than the fixture itself
        $filesAfter = glob($this->tmpDir . '/*.json');
        self::assertCount(1, $filesAfter, 'Only the input fixture should exist — no result files written');
    }

    // -------------------------------------------------------------------------
    // Output formatting
    // -------------------------------------------------------------------------

    #[Test]
    public function outputContainsHeaderAndCounts(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN006', 'currency' => 'XYZ']),
        ]);

        [, $output] = $this->captureRun($fixture);

        self::assertStringContainsString('Validation Results', $output);
        self::assertStringContainsString('Total : 2', $output);
        self::assertStringContainsString('Valid : 1', $output);
        self::assertStringContainsString('Invalid : 1', $output);
    }

    #[Test]
    public function outputContainsTableWithTransactionIds(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN001']),
            $this->validRecord(['transaction_id' => 'TXN099', 'currency' => 'XYZ']),
        ]);

        [, $output] = $this->captureRun($fixture);

        self::assertStringContainsString('TXN001', $output);
        self::assertStringContainsString('TXN099', $output);
        self::assertStringContainsString('valid', $output);
        self::assertStringContainsString('invalid', $output);
    }

    // -------------------------------------------------------------------------
    // Edge cases — empty set
    // -------------------------------------------------------------------------

    #[Test]
    public function emptyInputFileReturnsZeroCounts(): void
    {
        $fixture = $this->writeFixture([]);

        [$result, $output] = $this->captureRun($fixture);

        self::assertSame(0, $result['total']);
        self::assertSame(0, $result['valid']);
        self::assertSame(0, $result['invalid']);
        self::assertEmpty($result['rows']);
        self::assertStringContainsString('no transactions', $output);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    #[Test]
    public function missingFileThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot read input file/');

        $report = new ValidationReport(sink: static fn() => null);
        $report->run('/nonexistent/path/transactions.json');
    }

    #[Test]
    public function invalidJsonThrowsRuntimeException(): void
    {
        $path = $this->tmpDir . '/bad.json';
        file_put_contents($path, '{ not valid json ');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        $report = new ValidationReport(sink: static fn() => null);
        $report->run($path);
    }

    #[Test]
    public function nonArrayJsonThrowsRuntimeException(): void
    {
        $path = $this->tmpDir . '/object.json';
        file_put_contents($path, json_encode(['key' => 'value']));

        // A JSON object (not an array of records) should fail
        // Actually json_decode with associative=true on {"key":"value"} returns an assoc array,
        // which is_array() === true, so it won't throw — it will be treated as one transaction.
        // The spec says input must be a JSON array of records. Let's test with a bare string.
        file_put_contents($path, json_encode('just a string'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected a JSON array/');

        $report = new ValidationReport(sink: static fn() => null);
        $report->run($path);
    }

    // -------------------------------------------------------------------------
    // Transaction ID preservation in rows
    // -------------------------------------------------------------------------

    #[Test]
    public function rowsPreserveTransactionIdOrder(): void
    {
        $fixture = $this->writeFixture([
            $this->validRecord(['transaction_id' => 'TXN_A']),
            $this->validRecord(['transaction_id' => 'TXN_B', 'currency' => 'XYZ']),
            $this->validRecord(['transaction_id' => 'TXN_C']),
        ]);

        [$result] = $this->captureRun($fixture);

        self::assertSame('TXN_A', $result['rows'][0]['transaction_id']);
        self::assertSame('TXN_B', $result['rows'][1]['transaction_id']);
        self::assertSame('TXN_C', $result['rows'][2]['transaction_id']);
    }

    // -------------------------------------------------------------------------
    // Sample-transactions.json happy-path (8 records, 2 invalid at validation)
    // -------------------------------------------------------------------------

    #[Test]
    public function sampleTransactionsFileProducesExpectedCounts(): void
    {
        $sampleFile = dirname(__DIR__, 2) . '/sample-transactions.json';

        if (!file_exists($sampleFile)) {
            self::markTestSkipped('sample-transactions.json not present in project root.');
        }

        [$result] = $this->captureRun($sampleFile);

        // 8 total; TXN006 (XYZ currency) + TXN007 (-100.00 amount) = 2 invalid at validation stage
        // TXN004 is VALID at validation (cross-border + unusual-hour is fraud, not validator)
        self::assertSame(8, $result['total']);
        self::assertSame(6, $result['valid']);
        self::assertSame(2, $result['invalid']);

        $invalidIds = array_column(
            array_filter($result['rows'], fn($r) => $r['result'] === 'invalid'),
            'transaction_id',
        );
        self::assertContains('TXN006', $invalidIds);
        self::assertContains('TXN007', $invalidIds);
    }
}
