<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class UpdateTicketTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 1.7
     */
    public function test_update_ticket_with_valid_data_returns_200(): void
    {
        $createResponse = $this->postJson('/tickets', $this->validTicketData());
        $id = json_decode((string) $createResponse->getBody(), true)['id'];

        $response = $this->putJson('/tickets/' . $id, ['subject' => 'Updated subject']);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Updated subject', $data['subject']);
        $this->assertSame($id, $data['id']);
    }

    /**
     * Req 1.8
     */
    public function test_update_ticket_with_invalid_data_returns_400(): void
    {
        $createResponse = $this->postJson('/tickets', $this->validTicketData());
        $id = json_decode((string) $createResponse->getBody(), true)['id'];

        $response = $this->putJson('/tickets/' . $id, ['customer_email' => 'not-an-email']);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Validation failed', $data['error']);
        $this->assertArrayHasKey('customer_email', $data['details']);
    }
}
