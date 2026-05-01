<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Ticket;
use App\Entities\TicketMetadata;
use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;
use App\Enums\Ticket\Source;
use App\Enums\Ticket\Status;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TicketTest extends TestCase
{
    private function makeRow(): array
    {
        return [
            'id'                        => 'uuid-1',
            'customer_id'               => 'cust-1',
            'customer_email'            => 'test@example.com',
            'customer_name'             => 'Test User',
            'subject'                   => 'Test subject',
            'description'               => 'Test description here',
            'category'                  => 'technical_issue',
            'priority'                  => 'medium',
            'status'                    => 'new',
            'assigned_to'               => null,
            'tags'                      => '["bug","urgent"]',
            'metadata_source'           => 'web_form',
            'metadata_browser'          => 'Chrome',
            'metadata_device_type'      => 'desktop',
            'classification_confidence' => null,
            'classification_reasoning'  => null,
            'classification_keywords'   => null,
            'created_at'                => '2024-01-01T00:00:00Z',
            'updated_at'                => '2024-01-01T00:00:00Z',
            'resolved_at'               => null,
        ];
    }

    public function test_from_row_hydrates_all_fields(): void
    {
        $ticket = Ticket::fromRow($this->makeRow());

        $this->assertSame('uuid-1', $ticket->id);
        $this->assertSame('cust-1', $ticket->customerId);
        $this->assertSame('test@example.com', $ticket->customerEmail);
        $this->assertSame(Category::TechnicalIssue, $ticket->category);
        $this->assertSame(Priority::Medium, $ticket->priority);
        $this->assertSame(Status::New, $ticket->status);
        $this->assertSame(['bug', 'urgent'], $ticket->tags);
        $this->assertSame(Source::WebForm, $ticket->metadata->source);
        $this->assertNull($ticket->resolvedAt);
    }

    public function test_to_row_round_trips(): void
    {
        $ticket = Ticket::fromRow($this->makeRow());
        $row    = $ticket->toRow();

        $this->assertSame('uuid-1', $row['id']);
        $this->assertSame('technical_issue', $row['category']);
        $this->assertSame('["bug","urgent"]', $row['tags']);
        $this->assertSame('web_form', $row['metadata_source']);
    }

    public function test_json_serialize_returns_expected_shape(): void
    {
        $ticket = Ticket::fromRow($this->makeRow());
        $data   = $ticket->jsonSerialize();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('classification', $data);
        $this->assertSame('web_form', $data['metadata']['source']);
        $this->assertNull($data['classification']['confidence']);
    }

    public function test_from_row_with_resolved_at(): void
    {
        $row               = $this->makeRow();
        $row['resolved_at'] = '2024-06-01T12:00:00Z';

        $ticket = Ticket::fromRow($row);

        $this->assertInstanceOf(CarbonImmutable::class, $ticket->resolvedAt);
    }
}
