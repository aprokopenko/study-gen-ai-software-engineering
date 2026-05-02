<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class DeleteTicketTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 1.9
     */
    public function test_delete_existing_ticket_returns_204(): void
    {
        $createResponse = $this->postJson('/tickets', $this->validTicketData());
        $id = json_decode((string) $createResponse->getBody(), true)['id'];

        $response = $this->delete('/tickets/' . $id);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertLessThanOrEqual(1, strlen((string) $response->getBody()));
    }

    /**
     * Req 1.10
     */
    public function test_delete_nonexistent_ticket_returns_404(): void
    {
        $response = $this->delete('/tickets/nonexistent-id-99999');

        $this->assertSame(404, $response->getStatusCode());
    }
}
