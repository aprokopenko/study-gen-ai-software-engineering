<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use Tests\Concerns\AppTestCase;

class HandshakeTest extends AppTestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/');

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertSame('Support Ticket API', $body['message']);
    }
}
