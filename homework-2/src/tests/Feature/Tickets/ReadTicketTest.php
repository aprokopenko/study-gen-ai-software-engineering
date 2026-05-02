<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class ReadTicketTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 1.3
     */
    public function test_list_tickets_returns_200_with_array(): void
    {
        $this->postJson('/tickets', $this->validTicketData());

        $response = $this->get('/tickets');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    /**
     * Req 1.4
     */
    public function test_list_tickets_with_filter_returns_matching_only(): void
    {
        $this->postJson('/tickets', $this->validTicketData(['category' => 'technical_issue']));
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'other@example.com',
            'category' => 'billing_question',
        ]));

        $response = $this->get('/tickets?category=technical_issue');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);

        foreach ($data as $ticket) {
            $this->assertSame('technical_issue', $ticket['category']);
        }
    }

    /**
     * Req 1.5
     */
    public function test_show_existing_ticket_returns_200(): void
    {
        $createResponse = $this->postJson('/tickets', $this->validTicketData());
        $created = json_decode((string) $createResponse->getBody(), true);
        $id = $created['id'];

        $response = $this->get('/tickets/' . $id);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame($id, $data['id']);
    }

    /**
     * Req 1.6
     */
    public function test_show_nonexistent_ticket_returns_404(): void
    {
        $response = $this->get('/tickets/nonexistent-id-12345');

        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Not found', $data['error']);
    }
}
