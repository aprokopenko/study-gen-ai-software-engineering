<?php

declare(strict_types=1);

namespace Tests\Transactions;

use Tests\Concerns\AppTestCase;

class ShowTransactionTest extends AppTestCase
{
    public function testReturnsTransactionById(): void
    {
        $tx = $this->seedTransaction();

        $body = $this->assertStatus(200, $this->get('/transactions/' . $tx['id']));
        $this->assertSame($tx['id'], $body['id']);
    }

    public function testReturns404WhenMissing(): void
    {
        $this->assertStatus(404, $this->get('/transactions/non-existent-id'));
    }
}
