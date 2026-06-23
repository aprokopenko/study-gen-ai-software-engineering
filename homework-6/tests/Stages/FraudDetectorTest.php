<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Stages;

use BankingPipeline\Config\FraudRules;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Stages\FraudDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FraudDetector stage.
 *
 * All tests use a temporary directory and a silent/capturing audit sink.
 * The real shared/ directory is never touched.
 *
 * Covered:
 *   - Happy path (low-risk, forwarded to settlement)
 *   - High-value rule in isolation (exactly at threshold and above)
 *   - Unusual-hour rule in isolation (overnight window boundaries)
 *   - Cross-border rule in isolation (missing country, foreign country, home country)
 *   - Exactly-at-cutoff score (60 → high-risk)
 *   - Combined-rule high-risk (unusual_hour + cross_border = 60)
 *   - Reason string format (contains rule names and score)
 *   - Audit logging (outcome recorded, no PII leakage)
 *   - run() queue orchestration (files moved, written to correct directories)
 */
final class FraudDetectorTest extends TestCase
{
    private string $tempDir;
    private FileQueue $queue;
    private array $logCapture;
    private AuditLogger $logger;
    private FraudDetector $detector;

    protected function setUp(): void
    {
        // Isolated temp directory — never touches the real shared/
        $this->tempDir = sys_get_temp_dir() . '/fraud_test_' . bin2hex(random_bytes(8));
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

        $this->detector = new FraudDetector(
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
    // process() — happy path (low-risk)
    // =========================================================================

    #[Test]
    public function processForwardsLowRiskTransactionToSettlement(): void
    {
        $message = $this->lowRiskMessage();

        $result = $this->detector->process($message);

        $this->assertSame('fraud_checked', $result['status']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    #[Test]
    public function processRecordsRiskScoreOnLowRiskTransaction(): void
    {
        $message = $this->lowRiskMessage();

        $result = $this->detector->process($message);

        $this->assertArrayHasKey('risk_score', $result);
        $this->assertLessThan(FraudRules::CUTOFF, $result['risk_score']);
    }

    #[Test]
    public function processRecordsEmptyReasonsForCleanTransaction(): void
    {
        $message = $this->lowRiskMessage();

        $result = $this->detector->process($message);

        $this->assertArrayHasKey('risk_reasons', $result);
        $this->assertSame([], $result['risk_reasons']);
    }

    #[Test]
    public function processPreservesOriginalFieldsOnLowRiskTransaction(): void
    {
        $message = $this->lowRiskMessage();

        $result = $this->detector->process($message);

        foreach ($message as $key => $value) {
            $this->assertArrayHasKey($key, $result, "Expected field '{$key}' to be preserved");
        }
    }

    // =========================================================================
    // process() — high-value rule
    // =========================================================================

    #[Test]
    public function processDoesNotTriggerHighValueRuleBelowThreshold(): void
    {
        // $9,999.99 — just below threshold; no high-value flag
        $message = $this->lowRiskMessage(['amount' => '9999.99']);

        $result = $this->detector->process($message);

        $this->assertNotContains(FraudRules::RULE_HIGH_VALUE, $result['risk_reasons']);
    }

    #[Test]
    public function processTriggersHighValueRuleAtExactThreshold(): void
    {
        // $10,000.00 — exactly at threshold; high-value fires (>= comparison)
        $message = $this->lowRiskMessage([
            'amount'   => FraudRules::HIGH_VALUE_THRESHOLD,
            'metadata' => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_HIGH_VALUE, $result['risk_reasons']);
    }

    #[Test]
    public function processTriggersHighValueRuleAboveThreshold(): void
    {
        $message = $this->lowRiskMessage([
            'amount'   => '15000.00',
            'metadata' => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_HIGH_VALUE, $result['risk_reasons']);
    }

    #[Test]
    public function processHighValueAloneAdds40Points(): void
    {
        // Domestic, daytime (09:00), high amount — only high_value fires
        $message = $this->lowRiskMessage([
            'amount'    => '10000.00',
            'timestamp' => '2026-03-16T09:00:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(FraudRules::WEIGHT_HIGH_VALUE, $result['risk_score']);
    }

    // =========================================================================
    // process() — unusual-hour rule (overnight window boundaries)
    // =========================================================================

    #[Test]
    public function processTriggersUnusualHourAtMidnight(): void
    {
        // 00:00 is the start of the overnight window
        $message = $this->lowRiskMessage([
            'timestamp' => '2026-03-16T00:00:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_UNUSUAL_HOUR, $result['risk_reasons']);
    }

    #[Test]
    public function processTriggersUnusualHourAt0559(): void
    {
        // 05:59 is the last minute of the overnight window
        $message = $this->lowRiskMessage([
            'timestamp' => '2026-03-16T05:59:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_UNUSUAL_HOUR, $result['risk_reasons']);
    }

    #[Test]
    public function processDoesNotTriggerUnusualHourAt0600(): void
    {
        // 06:00 is just outside the overnight window — must NOT trigger
        $message = $this->lowRiskMessage([
            'timestamp' => '2026-03-16T06:00:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertNotContains(FraudRules::RULE_UNUSUAL_HOUR, $result['risk_reasons']);
    }

    #[Test]
    public function processDoesNotTriggerUnusualHourAtNoon(): void
    {
        $message = $this->lowRiskMessage([
            'timestamp' => '2026-03-16T12:00:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertNotContains(FraudRules::RULE_UNUSUAL_HOUR, $result['risk_reasons']);
    }

    #[Test]
    public function processUnusualHourAloneAdds30Points(): void
    {
        // Domestic, overnight (03:00), small amount — only unusual_hour fires
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T03:00:00Z',
            'metadata'  => ['country' => FraudRules::HOME_COUNTRY],
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(FraudRules::WEIGHT_UNUSUAL_HOUR, $result['risk_score']);
    }

    // =========================================================================
    // process() — cross-border rule (missing country, foreign country, home country)
    // =========================================================================

    #[Test]
    public function processTriggersCrossBorderWhenCountryIsMissing(): void
    {
        // No metadata at all — missing country counts as cross-border
        $message = $this->lowRiskMessage();
        unset($message['metadata']);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_CROSS_BORDER, $result['risk_reasons']);
    }

    #[Test]
    public function processTriggersCrossBorderWhenMetadataExistsButCountryMissing(): void
    {
        // metadata present but country key absent
        $message = $this->lowRiskMessage(['metadata' => ['channel' => 'online']]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_CROSS_BORDER, $result['risk_reasons']);
    }

    #[Test]
    public function processTriggersCrossBorderForForeignCountry(): void
    {
        $message = $this->lowRiskMessage(['metadata' => ['country' => 'GB']]);

        $result = $this->detector->process($message);

        $this->assertContains(FraudRules::RULE_CROSS_BORDER, $result['risk_reasons']);
    }

    #[Test]
    public function processDoesNotTriggerCrossBorderForHomeCountry(): void
    {
        $message = $this->lowRiskMessage(['metadata' => ['country' => FraudRules::HOME_COUNTRY]]);

        $result = $this->detector->process($message);

        $this->assertNotContains(FraudRules::RULE_CROSS_BORDER, $result['risk_reasons']);
    }

    #[Test]
    public function processCrossBorderAloneAdds30Points(): void
    {
        // Daytime, small amount, foreign country — only cross_border fires
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T09:00:00Z',
            'metadata'  => ['country' => 'CA'],
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(FraudRules::WEIGHT_CROSS_BORDER, $result['risk_score']);
    }

    // =========================================================================
    // process() — cutoff boundary (score exactly at 60 → high-risk)
    // =========================================================================

    #[Test]
    public function processExactlyCutoffScoreIsHighRisk(): void
    {
        // unusual_hour (30) + cross_border (30) = 60 — exactly at cutoff → rejected
        // Spec §3 line 146: high-risk transactions use status=rejected in results/
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',       // below high-value threshold
            'timestamp' => '2026-03-16T03:00:00Z', // overnight
            'metadata'  => ['country' => 'DE'],   // cross-border
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(60, $result['risk_score']);
        $this->assertSame('rejected', $result['status']);
    }

    #[Test]
    public function processScoreBelowCutoffIsLowRisk(): void
    {
        // Only cross_border fires → score = 30 (< 60)
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T09:00:00Z',
            'metadata'  => ['country' => 'DE'],
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(30, $result['risk_score']);
        $this->assertSame('fraud_checked', $result['status']);
    }

    #[Test]
    public function processAllThreeRulesFireScore100(): void
    {
        // high_value(40) + unusual_hour(30) + cross_border(30) = 100 → rejected
        $message = $this->lowRiskMessage([
            'amount'    => '15000.00',
            'timestamp' => '2026-03-16T02:00:00Z',
            'metadata'  => ['country' => 'CN'],
        ]);

        $result = $this->detector->process($message);

        $this->assertSame(100, $result['risk_score']);
        $this->assertSame('rejected', $result['status']);
    }

    // =========================================================================
    // process() — high-risk reason string content
    // =========================================================================

    #[Test]
    public function processHighRiskReasonContainsTriggeredRuleNames(): void
    {
        // unusual_hour + cross_border = 60
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T01:00:00Z',
            'metadata'  => ['country' => 'FR'],
        ]);

        $result = $this->detector->process($message);

        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString(FraudRules::RULE_UNUSUAL_HOUR, $result['reason']);
        $this->assertStringContainsString(FraudRules::RULE_CROSS_BORDER, $result['reason']);
    }

    #[Test]
    public function processHighRiskReasonContainsScore(): void
    {
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T01:00:00Z',
            'metadata'  => ['country' => 'FR'],
        ]);

        $result = $this->detector->process($message);

        $this->assertStringContainsString('60', $result['reason']);
    }

    #[Test]
    public function processLowRiskHasNoReasonField(): void
    {
        $message = $this->lowRiskMessage();

        $result = $this->detector->process($message);

        $this->assertArrayNotHasKey('reason', $result);
    }

    // =========================================================================
    // process() — audit logging
    // =========================================================================

    #[Test]
    public function processLogsHighRiskOutcome(): void
    {
        $message = $this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T01:00:00Z',
            'metadata'  => ['country' => 'JP'],
        ]);

        $this->detector->process($message);

        $this->assertCount(1, $this->logCapture);
        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame(self::STAGE_NAME(), $entry['step']);
        $this->assertSame('TXN001', $entry['transaction_id']);
        $this->assertSame('high_risk', $entry['outcome']);
    }

    #[Test]
    public function processLogsFraudCheckedOutcome(): void
    {
        $message = $this->lowRiskMessage();

        $this->detector->process($message);

        $this->assertCount(1, $this->logCapture);
        $entry = json_decode($this->logCapture[0], associative: true);
        $this->assertSame(self::STAGE_NAME(), $entry['step']);
        $this->assertSame('fraud_checked', $entry['outcome']);
    }

    #[Test]
    public function processDoesNotLogSourceAccountPii(): void
    {
        $message = $this->lowRiskMessage(['source_account' => 'ACC-SENSITIVE-1001']);

        $this->detector->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-SENSITIVE-1001',
            $logLine,
            'source_account PII must not appear in the audit log'
        );
    }

    #[Test]
    public function processDoesNotLogDestinationAccountPii(): void
    {
        $message = $this->lowRiskMessage(['destination_account' => 'ACC-SENSITIVE-9999']);

        $this->detector->process($message);

        $logLine = implode('', $this->logCapture);
        $this->assertStringNotContainsString(
            'ACC-SENSITIVE-9999',
            $logLine,
            'destination_account PII must not appear in the audit log'
        );
    }

    // =========================================================================
    // run() — queue orchestration
    // =========================================================================

    #[Test]
    public function runForwardsLowRiskToOutput(): void
    {
        $this->dropEnvelopeInOutput($this->lowRiskMessage());

        $counts = $this->detector->run();

        $this->assertSame(1, $counts['passed']);
        $this->assertSame(0, $counts['rejected']);

        $outputFiles = $this->queue->listFiles(FileQueue::DIR_OUTPUT);
        $this->assertCount(1, $outputFiles);

        $resultEnvelope = $this->queue->read($outputFiles[0], FileQueue::DIR_OUTPUT);
        $this->assertSame('fraud_checked', $resultEnvelope->data['status']);
        $this->assertSame('fraud_detector', $resultEnvelope->source);
        $this->assertSame('settlement', $resultEnvelope->target);
    }

    #[Test]
    public function runWritesHighRiskToResults(): void
    {
        // unusual_hour + cross_border = 60 → high-risk
        $this->dropEnvelopeInOutput($this->lowRiskMessage([
            'amount'    => '500.00',
            'timestamp' => '2026-03-16T02:00:00Z',
            'metadata'  => ['country' => 'MX'],
        ]));

        $counts = $this->detector->run();

        $this->assertSame(0, $counts['passed']);
        $this->assertSame(1, $counts['rejected']);

        $resultsFiles = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $this->assertCount(1, $resultsFiles);

        $resultEnvelope = $this->queue->read($resultsFiles[0], FileQueue::DIR_RESULTS);
        // Persisted status must be 'rejected' (spec §3: uniform shape for Reporter/MCP)
        $this->assertSame('rejected', $resultEnvelope->data['status']);
        $this->assertArrayHasKey('reason', $resultEnvelope->data);
    }

    #[Test]
    public function runClearsProcessingAfterCompletion(): void
    {
        $this->dropEnvelopeInOutput($this->lowRiskMessage());

        $this->detector->run();

        $processingFiles = $this->queue->listFiles(FileQueue::DIR_PROCESSING);
        $this->assertCount(0, $processingFiles, 'Processing directory should be empty after run');
    }

    #[Test]
    public function runHandlesMultipleMessages(): void
    {
        // 2 low-risk + 1 high-risk
        $this->dropEnvelopeInOutput($this->lowRiskMessage(['transaction_id' => 'TXN001']));
        $this->dropEnvelopeInOutput($this->lowRiskMessage(['transaction_id' => 'TXN002']));
        $this->dropEnvelopeInOutput($this->lowRiskMessage([
            'transaction_id' => 'TXN003',
            'amount'         => '500.00',
            'timestamp'      => '2026-03-16T02:00:00Z',
            'metadata'       => ['country' => 'MX'],
        ]));

        $counts = $this->detector->run();

        $this->assertSame(2, $counts['passed']);
        $this->assertSame(1, $counts['rejected']);
    }

    #[Test]
    public function runReturnsZeroCountsWhenOutputIsEmpty(): void
    {
        $counts = $this->detector->run();

        $this->assertSame(0, $counts['passed']);
        $this->assertSame(0, $counts['rejected']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a fully low-risk transaction message (daytime, domestic, small amount).
     *
     * Defaults: 09:00 UTC (daytime), USD domestic (US), $500 (well below threshold).
     * Override any field via $overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function lowRiskMessage(array $overrides = []): array
    {
        $base = [
            'transaction_id'      => 'TXN001',
            'timestamp'           => '2026-03-16T09:00:00Z',
            'source_account'      => 'ACC-1001',
            'destination_account' => 'ACC-2001',
            'amount'              => '500.00',
            'currency'            => 'USD',
            'transaction_type'    => 'transfer',
            'status'              => 'validated',
            'metadata'            => ['country' => 'US'],
        ];

        // Merge top-level overrides; metadata is merged separately to allow
        // partial overrides of the nested array
        foreach ($overrides as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Wrap a message in an envelope and drop it into shared/output
     * (the hand-off directory from the Validator, which FraudDetector reads).
     *
     * @param array<string, mixed> $data
     */
    private function dropEnvelopeInOutput(array $data): void
    {
        $envelope = Envelope::create(
            source: 'validator',
            target: 'fraud_detector',
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

    /**
     * Return the expected stage name constant for assertions.
     */
    private static function STAGE_NAME(): string
    {
        return 'fraud_detector';
    }
}
