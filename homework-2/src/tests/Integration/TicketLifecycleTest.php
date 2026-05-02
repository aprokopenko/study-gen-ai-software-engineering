<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class TicketLifecycleTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 14.1
     */
    public function test_full_ticket_lifecycle(): void
    {
        // Create
        $response = $this->postJson('/tickets', $this->validTicketData());
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $id = $data['id'];

        // Read
        $response = $this->get('/tickets/' . $id);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame($id, $data['id']);

        // Update
        $response = $this->putJson('/tickets/' . $id, ['subject' => 'Updated lifecycle subject']);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Updated lifecycle subject', $data['subject']);

        // Verify update persisted
        $response = $this->get('/tickets/' . $id);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Updated lifecycle subject', $data['subject']);

        // Delete
        $response = $this->delete('/tickets/' . $id);
        $this->assertSame(204, $response->getStatusCode());

        // Confirm gone
        $response = $this->get('/tickets/' . $id);
        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Req 14.2
     */
    public function test_import_then_list(): void
    {
        $content = file_get_contents(__DIR__ . '/../fixtures/valid/sample_tickets.csv');

        // Import
        $response = $this->postRaw('/tickets/import', $content, 'text/csv');
        $this->assertSame(200, $response->getStatusCode());
        $summary = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $summary['successful']);

        // List
        $response = $this->get('/tickets');
        $this->assertSame(200, $response->getStatusCode());
        $tickets = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThanOrEqual($summary['successful'], count($tickets));
    }

    /**
     * Req 14.3
     */
    public function test_combined_category_and_priority_filter(): void
    {
        // Create ticket A: technical_issue + high
        $this->postJson('/tickets', $this->validTicketData([
            'category' => 'technical_issue',
            'priority' => 'high',
        ]));

        // Create ticket B: technical_issue + low
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'b@example.com',
            'category' => 'technical_issue',
            'priority' => 'low',
        ]));

        // Create ticket C: billing_question + high
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'c@example.com',
            'category' => 'billing_question',
            'priority' => 'high',
        ]));

        // Filter by category=technical_issue&priority=high
        $response = $this->get('/tickets?category=technical_issue&priority=high');
        $this->assertSame(200, $response->getStatusCode());

        $tickets = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThanOrEqual(1, count($tickets));

        foreach ($tickets as $ticket) {
            $this->assertSame('technical_issue', $ticket['category']);
            $this->assertSame('high', $ticket['priority']);
        }
    }

    /**
     * Req 14.4
     */
    public function test_create_with_auto_classify_then_read_classification(): void
    {
        // Create with auto-classify
        $response = $this->postJson('/tickets?auto_classify=true', $this->validTicketData());
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $id = $data['id'];

        // Read back
        $response = $this->get('/tickets/' . $id);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertIsArray($data['classification']);
        $this->assertNotNull($data['classification']['confidence']);
    }
}
