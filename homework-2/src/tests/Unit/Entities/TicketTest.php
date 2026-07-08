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
use Tests\Traits\TicketDataBuilder;

class TicketTest extends TestCase
{
    use TicketDataBuilder;

    private function makeRow(): array
    {
        return [
            'id' => 'uuid-1',
            'customer_id' => 'cust-1',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'subject' => 'Test subject',
            'description' => 'Test description here',
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'new',
            'assigned_to' => null,
            'tags' => '["bug","urgent"]',
            'metadata_source' => 'web_form',
            'metadata_browser' => 'Chrome',
            'metadata_device_type' => 'desktop',
            'classification_confidence' => null,
            'classification_reasoning' => null,
            'classification_keywords' => null,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-01T00:00:00Z',
            'resolved_at' => null,
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
        $row = $ticket->toRow();

        $this->assertSame('uuid-1', $row['id']);
        $this->assertSame('technical_issue', $row['category']);
        $this->assertSame('["bug","urgent"]', $row['tags']);
        $this->assertSame('web_form', $row['metadata_source']);
    }

    public function test_json_serialize_returns_expected_shape(): void
    {
        $ticket = Ticket::fromRow($this->makeRow());
        $data = $ticket->jsonSerialize();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('classification', $data);
        $this->assertSame('web_form', $data['metadata']['source']);
        $this->assertNull($data['classification']['confidence']);
    }

    public function test_from_row_with_resolved_at(): void
    {
        $row = $this->makeRow();
        $row['resolved_at'] = '2024-06-01T12:00:00Z';

        $ticket = Ticket::fromRow($row);

        $this->assertInstanceOf(CarbonImmutable::class, $ticket->resolvedAt);
    }

    // --- New round-trip property tests (Req 2.1–2.5) ---

    /**
     * Validates: Requirements 2.1
     */
    public function test_constructor_accepts_all_required_parameters(): void
    {
        $ticket = new Ticket(
            id: 'test-id',
            customerId: 'cust-1',
            customerEmail: 'user@example.com',
            customerName: 'Test User',
            subject: 'Test subject',
            description: 'Test description here',
            category: Category::TechnicalIssue,
            priority: Priority::Medium,
            status: Status::New,
            assignedTo: null,
            tags: [],
            metadata: new TicketMetadata(),
            classificationConfidence: null,
            classificationReasoning: null,
            classificationKeywords: null,
            createdAt: CarbonImmutable::now(),
            updatedAt: CarbonImmutable::now(),
            resolvedAt: null,
        );

        $this->assertIsString($ticket->id);
        $this->assertInstanceOf(Category::class, $ticket->category);
        $this->assertInstanceOf(Priority::class, $ticket->priority);
        $this->assertInstanceOf(Status::class, $ticket->status);
        $this->assertInstanceOf(TicketMetadata::class, $ticket->metadata);
        $this->assertInstanceOf(CarbonImmutable::class, $ticket->createdAt);
    }

    /**
     * Validates: Requirements 2.2
     */
    public function test_json_serialize_converts_enums_to_backing_values(): void
    {
        $ticket = Ticket::fromRow($this->validTicketRow());
        $data = $ticket->jsonSerialize();

        $this->assertIsString($data['category']);
        $this->assertIsString($data['priority']);
        $this->assertIsString($data['status']);
        $this->assertIsString($data['metadata']['source']);
        $this->assertIsString($data['metadata']['device_type']);
    }

    /**
     * Validates: Requirements 2.3
     */
    public function test_from_row_maps_all_fields_correctly(): void
    {
        $ticket = Ticket::fromRow($this->validTicketRow([
            'classification_confidence' => 0.85,
            'classification_reasoning' => 'test reason',
            'classification_keywords' => '["kw1","kw2"]',
            'resolved_at' => '2024-06-01T12:00:00+00:00',
        ]));

        $this->assertSame(0.85, $ticket->classificationConfidence);
        $this->assertSame('test reason', $ticket->classificationReasoning);
        $this->assertSame(['kw1', 'kw2'], $ticket->classificationKeywords);
        $this->assertInstanceOf(CarbonImmutable::class, $ticket->resolvedAt);
    }

    /**
     * Validates: Requirements 2.4
     */
    public function test_to_row_returns_database_ready_array(): void
    {
        $ticket = Ticket::fromRow($this->validTicketRow());
        $row = $ticket->toRow();

        $this->assertIsString($row['tags']);
        $this->assertIsString($row['category']);
        $this->assertIsString($row['priority']);
        $this->assertIsString($row['status']);
        $this->assertTrue(array_key_exists('metadata_source', $row));
        $this->assertTrue(array_key_exists('metadata_browser', $row));
        $this->assertTrue(array_key_exists('metadata_device_type', $row));
    }

    /**
     * Validates: Requirements 2.5
     */
    public function test_to_row_then_from_row_round_trip(): void
    {
        $ticket = Ticket::fromRow($this->validTicketRow());
        $row = $ticket->toRow();
        $ticket2 = Ticket::fromRow($row);

        $this->assertSame($ticket->id, $ticket2->id);
        $this->assertSame($ticket->customerEmail, $ticket2->customerEmail);
        $this->assertSame($ticket->category, $ticket2->category);
        $this->assertSame($ticket->priority, $ticket2->priority);
        $this->assertSame($ticket->status, $ticket2->status);
        $this->assertSame($ticket->tags, $ticket2->tags);
        $this->assertSame($ticket->metadata->source, $ticket2->metadata->source);
    }
}
