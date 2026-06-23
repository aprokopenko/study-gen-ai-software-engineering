<?php

declare(strict_types=1);

namespace BankingPipeline\Shared;

use InvalidArgumentException;

/**
 * Standard JSON envelope for all inter-stage messages.
 *
 * Every file passed between pipeline stages uses this shape:
 * {
 *   "message_id": "uuid4-string",
 *   "timestamp": "ISO-8601",
 *   "source": "validator",
 *   "target": "fraud_detector",
 *   "type": "transaction",
 *   "data": { ... }
 * }
 */
final class Envelope
{
    private function __construct(
        public readonly string $messageId,
        public readonly string $timestamp,
        public readonly string $source,
        public readonly string $target,
        public readonly string $type,
        public readonly array $data,
    ) {}

    /**
     * Create a new envelope with a generated UUID v4 message ID and current timestamp.
     */
    public static function create(
        string $source,
        string $target,
        string $type,
        array $data,
    ): self {
        return new self(
            messageId: self::generateUuid4(),
            timestamp: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            source: $source,
            target: $target,
            type: $type,
            data: $data,
        );
    }

    /**
     * Parse an envelope from a JSON string.
     *
     * @throws InvalidArgumentException on malformed JSON or missing required fields.
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Envelope JSON must be an object.');
        }

        $required = ['message_id', 'timestamp', 'source', 'target', 'type', 'data'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $decoded)) {
                throw new InvalidArgumentException("Envelope is missing required field: {$field}");
            }
        }

        if (!is_array($decoded['data'])) {
            throw new InvalidArgumentException('Envelope "data" field must be an object.');
        }

        return new self(
            messageId: (string) $decoded['message_id'],
            timestamp: (string) $decoded['timestamp'],
            source: (string) $decoded['source'],
            target: (string) $decoded['target'],
            type: (string) $decoded['type'],
            data: $decoded['data'],
        );
    }

    /**
     * Serialize this envelope to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode([
            'message_id' => $this->messageId,
            'timestamp'  => $this->timestamp,
            'source'     => $this->source,
            'target'     => $this->target,
            'type'       => $this->type,
            'data'       => $this->data,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a UUID v4 string using random bytes.
     */
    private static function generateUuid4(): string
    {
        $bytes = random_bytes(16);

        // Set version bits to 4 (0100xxxx)
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant bits to 10xx
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
