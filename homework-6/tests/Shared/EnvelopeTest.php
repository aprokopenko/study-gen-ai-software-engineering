<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Shared;

use BankingPipeline\Shared\Envelope;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Envelope::create()
    // -------------------------------------------------------------------------

    #[Test]
    public function createReturnsEnvelopeWithAllFields(): void
    {
        $data     = ['transaction_id' => 'TXN001', 'amount' => '1500.00'];
        $envelope = Envelope::create('validator', 'fraud_detector', 'transaction', $data);

        $this->assertSame('validator', $envelope->source);
        $this->assertSame('fraud_detector', $envelope->target);
        $this->assertSame('transaction', $envelope->type);
        $this->assertSame($data, $envelope->data);
    }

    #[Test]
    public function createGeneratesUuid4MessageId(): void
    {
        $envelope = Envelope::create('a', 'b', 'c', []);

        // UUID v4: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $envelope->messageId,
        );
    }

    #[Test]
    public function createGeneratesIso8601Timestamp(): void
    {
        $envelope = Envelope::create('a', 'b', 'c', []);

        // Must parse as a valid DateTimeImmutable
        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $envelope->timestamp);
        $this->assertNotFalse($dt, "Timestamp '{$envelope->timestamp}' is not valid ISO-8601.");
    }

    #[Test]
    public function createGeneratesUniqueMessageIds(): void
    {
        $a = Envelope::create('a', 'b', 'c', []);
        $b = Envelope::create('a', 'b', 'c', []);

        $this->assertNotSame($a->messageId, $b->messageId);
    }

    // -------------------------------------------------------------------------
    // Envelope::fromJson() — happy path
    // -------------------------------------------------------------------------

    #[Test]
    public function fromJsonParsesValidEnvelope(): void
    {
        $json = json_encode([
            'message_id' => 'abc-123',
            'timestamp'  => '2026-03-16T09:00:00+00:00',
            'source'     => 'validator',
            'target'     => 'fraud_detector',
            'type'       => 'transaction',
            'data'       => ['transaction_id' => 'TXN001'],
        ]);

        $envelope = Envelope::fromJson($json);

        $this->assertSame('abc-123', $envelope->messageId);
        $this->assertSame('2026-03-16T09:00:00+00:00', $envelope->timestamp);
        $this->assertSame('validator', $envelope->source);
        $this->assertSame('fraud_detector', $envelope->target);
        $this->assertSame('transaction', $envelope->type);
        $this->assertSame(['transaction_id' => 'TXN001'], $envelope->data);
    }

    // -------------------------------------------------------------------------
    // Envelope::fromJson() — malformed / missing fields
    // -------------------------------------------------------------------------

    #[Test]
    public function fromJsonThrowsOnMalformedJson(): void
    {
        $this->expectException(\JsonException::class);
        Envelope::fromJson('{not valid json}');
    }

    #[Test]
    public function fromJsonThrowsOnNonObjectJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be an object/i');
        Envelope::fromJson('"just a string"');
    }

    #[Test]
    #[DataProvider('missingFieldProvider')]
    public function fromJsonThrowsOnMissingField(string $missingField): void
    {
        $base = [
            'message_id' => 'abc',
            'timestamp'  => '2026-01-01T00:00:00+00:00',
            'source'     => 's',
            'target'     => 't',
            'type'       => 'transaction',
            'data'       => [],
        ];

        unset($base[$missingField]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing required field/i');
        Envelope::fromJson(json_encode($base));
    }

    public static function missingFieldProvider(): array
    {
        return [
            'missing message_id' => ['message_id'],
            'missing timestamp'  => ['timestamp'],
            'missing source'     => ['source'],
            'missing target'     => ['target'],
            'missing type'       => ['type'],
            'missing data'       => ['data'],
        ];
    }

    #[Test]
    public function fromJsonThrowsWhenDataIsNotAnObject(): void
    {
        $json = json_encode([
            'message_id' => 'abc',
            'timestamp'  => '2026-01-01T00:00:00+00:00',
            'source'     => 's',
            'target'     => 't',
            'type'       => 'transaction',
            'data'       => 'should be an object',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/data.*object/i');
        Envelope::fromJson($json);
    }

    // -------------------------------------------------------------------------
    // Round-trip: create → toJson → fromJson
    // -------------------------------------------------------------------------

    #[Test]
    public function roundTripPreservesAllFields(): void
    {
        $data     = ['transaction_id' => 'TXN042', 'amount' => '99.99'];
        $original = Envelope::create('validator', 'fraud_detector', 'transaction', $data);

        $restored = Envelope::fromJson($original->toJson());

        $this->assertSame($original->messageId, $restored->messageId);
        $this->assertSame($original->timestamp, $restored->timestamp);
        $this->assertSame($original->source, $restored->source);
        $this->assertSame($original->target, $restored->target);
        $this->assertSame($original->type, $restored->type);
        $this->assertSame($original->data, $restored->data);
    }
}
