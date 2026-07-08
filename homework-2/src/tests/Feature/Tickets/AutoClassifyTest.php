<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class AutoClassifyTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 1.12
     */
    public function test_auto_classify_existing_ticket_returns_200_with_classification(): void
    {
        $createResponse = $this->postJson('/tickets', $this->validTicketData());
        $id = json_decode((string) $createResponse->getBody(), true)['id'];

        $response = $this->postJson('/tickets/' . $id . '/auto-classify', []);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('confidence', $data);
        $this->assertArrayHasKey('reasoning', $data);
        $this->assertArrayHasKey('keywords', $data);
    }

    /**
     * Req 1.13
     */
    public function test_auto_classify_nonexistent_ticket_returns_404(): void
    {
        $response = $this->postJson('/tickets/nonexistent-id-99999/auto-classify', []);

        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Not found', $data['error']);
    }
}
