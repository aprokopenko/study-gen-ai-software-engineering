<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Ticket\DeviceType;
use App\Enums\Ticket\Source;

readonly class TicketMetadata
{
    public function __construct(
        public ?Source     $source     = null,
        public ?string     $browser    = null,
        public ?DeviceType $deviceType = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            source:     isset($row['metadata_source'])      ? Source::tryFrom($row['metadata_source'])         : null,
            browser:    $row['metadata_browser']            ?? null,
            deviceType: isset($row['metadata_device_type']) ? DeviceType::tryFrom($row['metadata_device_type']) : null,
        );
    }

    public function toRow(): array
    {
        return [
            'metadata_source'      => $this->source?->value,
            'metadata_browser'     => $this->browser,
            'metadata_device_type' => $this->deviceType?->value,
        ];
    }
}
