<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class CreateTicketTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 1.1
     */
    public function test_create_ticket_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/tickets', $this->validTicketData());

        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['id']);
        $this->assertIsString($data['id']);
        $this->assertSame('test@example.com', $data['customer_email']);
        $this->assertSame('Test ticket subject', $data['subject']);
    }

    /**
     * Req 1.2
     */
    public function test_create_ticket_with_invalid_data_returns_400(): void
    {
        $response = $this->postJson('/tickets', []);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Validation failed', $data['error']);
        $this->assertIsArray($data['details']);
    }

    /**
     * Req 1.2
     */
    public function test_create_ticket_with_invalid_email_returns_400(): void
    {
        $response = $this->postJson('/tickets', $this->validTicketData(['customer_email' => 'not-an-email']));

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Validation failed', $data['error']);
        $this->assertArrayHasKey('customer_email', $data['details']);
    }

    /**
     * Req 1.11
     */
    public function test_create_ticket_with_auto_classify_returns_classification(): void
    {
        $response = $this->postJson('/tickets?auto_classify=true', $this->validTicketData());

        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data['classification']);
        $this->assertNotNull($data['classification']['confidence']);
    }
}
