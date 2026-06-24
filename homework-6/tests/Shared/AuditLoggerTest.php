<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Shared;

use BankingPipeline\Shared\AuditLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Basic logging — happy path
    // -------------------------------------------------------------------------

    #[Test]
    public function logWritesJsonLineToSink(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated');

        $this->assertCount(1, $captured);
        $entry = json_decode($captured[0], associative: true);
        $this->assertIsArray($entry);
    }

    #[Test]
    public function logIncludesRequiredFields(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', []);

        $entry = json_decode($captured[0], associative: true);

        $this->assertArrayHasKey('timestamp', $entry);
        $this->assertArrayHasKey('step', $entry);
        $this->assertArrayHasKey('transaction_id', $entry);
        $this->assertArrayHasKey('outcome', $entry);
        $this->assertSame('validator', $entry['step']);
        $this->assertSame('TXN001', $entry['transaction_id']);
        $this->assertSame('validated', $entry['outcome']);
    }

    #[Test]
    public function logTimestampIsIso8601(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated');
        $entry = json_decode($captured[0], associative: true);

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $entry['timestamp']);
        $this->assertNotFalse($dt, "Timestamp '{$entry['timestamp']}' is not valid ISO-8601.");
    }

    // -------------------------------------------------------------------------
    // PII masking — account fields must never appear in plaintext
    // -------------------------------------------------------------------------

    #[Test]
    public function logMasksSourceAccount(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', [
            'source_account' => 'ACC-1001',
        ]);

        $line = $captured[0];

        $this->assertStringNotContainsString('ACC-1001', $line, 'source_account must not appear in plaintext');
        $this->assertStringContainsString('[MASKED:', $line, 'Expected masked placeholder in log output');
    }

    #[Test]
    public function logMasksDestinationAccount(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', [
            'destination_account' => 'ACC-2001',
        ]);

        $line = $captured[0];

        $this->assertStringNotContainsString('ACC-2001', $line, 'destination_account must not appear in plaintext');
        $this->assertStringContainsString('[MASKED:', $line);
    }

    #[Test]
    public function logMasksDescriptionField(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', [
            'description' => 'Monthly rent payment',
        ]);

        $line = $captured[0];

        $this->assertStringNotContainsString('Monthly rent payment', $line, 'description must not appear in plaintext');
        $this->assertStringContainsString('[MASKED:', $line);
    }

    #[Test]
    public function logMasksNameField(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', [
            'customer_name' => 'John Doe',
        ]);

        $line = $captured[0];

        $this->assertStringNotContainsString('John Doe', $line, 'customer_name must not appear in plaintext');
        $this->assertStringContainsString('[MASKED:', $line);
    }

    #[Test]
    public function logDoesNotMaskNonPiiFields(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', [
            'currency' => 'USD',
            'amount'   => '1500.00',
            'status'   => 'validated',
        ]);

        $line = $captured[0];

        $this->assertStringContainsString('USD', $line, 'Non-PII field currency should be visible');
        $this->assertStringContainsString('1500.00', $line, 'Non-PII field amount should be visible');
        $this->assertStringContainsString('validated', $line, 'Non-PII field status should be visible');
    }

    #[Test]
    public function maskIsConsistentForSameValue(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', ['source_account' => 'ACC-1001']);
        $logger->log('validator', 'TXN002', 'validated', ['source_account' => 'ACC-1001']);

        $entry1 = json_decode($captured[0], associative: true);
        $entry2 = json_decode($captured[1], associative: true);

        $masked1 = $entry1['context']['source_account'];
        $masked2 = $entry2['context']['source_account'];

        $this->assertSame($masked1, $masked2, 'Same account value must produce same masked token');
    }

    #[Test]
    public function maskDiffersForDifferentValues(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'validated', ['source_account' => 'ACC-1001']);
        $logger->log('validator', 'TXN002', 'validated', ['source_account' => 'ACC-2002']);

        $entry1 = json_decode($captured[0], associative: true);
        $entry2 = json_decode($captured[1], associative: true);

        $this->assertNotSame(
            $entry1['context']['source_account'],
            $entry2['context']['source_account'],
            'Different account values must produce different masked tokens',
        );
    }

    // -------------------------------------------------------------------------
    // Nested context PII masking
    // -------------------------------------------------------------------------

    #[Test]
    public function logMasksPiiInNestedContext(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('validator', 'TXN001', 'rejected', [
            'transaction' => [
                'source_account'      => 'ACC-1001',
                'destination_account' => 'ACC-2001',
                'amount'              => '1500.00',
            ],
        ]);

        $line = $captured[0];

        $this->assertStringNotContainsString('ACC-1001', $line, 'Nested source_account must be masked');
        $this->assertStringNotContainsString('ACC-2001', $line, 'Nested destination_account must be masked');
        $this->assertStringContainsString('1500.00', $line, 'Nested amount should be visible');
    }

    // -------------------------------------------------------------------------
    // Empty context
    // -------------------------------------------------------------------------

    #[Test]
    public function logWithEmptyContextStillLogs(): void
    {
        $captured = [];
        $logger   = new AuditLogger(sink: function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        $logger->log('settlement', 'TXN003', 'settled');

        $this->assertCount(1, $captured);
        $entry = json_decode($captured[0], associative: true);
        $this->assertSame('settlement', $entry['step']);
        $this->assertSame('TXN003', $entry['transaction_id']);
        $this->assertSame('settled', $entry['outcome']);
    }
}
