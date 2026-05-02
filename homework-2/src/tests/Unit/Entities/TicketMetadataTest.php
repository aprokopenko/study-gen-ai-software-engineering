<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\TicketMetadata;
use App\Enums\Ticket\DeviceType;
use App\Enums\Ticket\Source;
use PHPUnit\Framework\TestCase;
use Tests\Traits\TicketDataBuilder;

class TicketMetadataTest extends TestCase
{
    use TicketDataBuilder;

    public function test_constructs_with_all_null_parameters(): void
    {
        $metadata = new TicketMetadata();

        $this->assertNull($metadata->source);
        $this->assertNull($metadata->browser);
        $this->assertNull($metadata->deviceType);
    }

    public function test_from_row_maps_metadata_columns(): void
    {
        $metadata = TicketMetadata::fromRow([
            'metadata_source' => 'web_form',
            'metadata_browser' => 'Chrome',
            'metadata_device_type' => 'desktop',
        ]);

        $this->assertSame(Source::WebForm, $metadata->source);
        $this->assertSame('Chrome', $metadata->browser);
        $this->assertSame(DeviceType::Desktop, $metadata->deviceType);
    }

    public function test_to_row_returns_prefixed_columns(): void
    {
        $metadata = new TicketMetadata(
            source: Source::WebForm,
            browser: 'Chrome',
            deviceType: DeviceType::Desktop,
        );

        $row = $metadata->toRow();

        $this->assertSame('web_form', $row['metadata_source']);
        $this->assertSame('Chrome', $row['metadata_browser']);
        $this->assertSame('desktop', $row['metadata_device_type']);

        $nullMetadata = new TicketMetadata();
        $nullRow = $nullMetadata->toRow();

        $this->assertNull($nullRow['metadata_source']);
        $this->assertNull($nullRow['metadata_browser']);
        $this->assertNull($nullRow['metadata_device_type']);
    }

    public function test_to_row_then_from_row_round_trip(): void
    {
        $metadata = new TicketMetadata(
            source: Source::Email,
            browser: 'Firefox',
            deviceType: DeviceType::Mobile,
        );

        $row = $metadata->toRow();
        $metadata2 = TicketMetadata::fromRow($row);

        $this->assertSame($metadata->source, $metadata2->source);
        $this->assertSame($metadata->browser, $metadata2->browser);
        $this->assertSame($metadata->deviceType, $metadata2->deviceType);
    }
}
