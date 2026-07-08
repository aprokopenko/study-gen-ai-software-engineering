<?php

declare(strict_types=1);

namespace Tests\Transactions;

use Tests\Concerns\AppTestCase;

class ListTransactionsTest extends AppTestCase
{
    public function testReturnsEmptyArrayWhenNoTransactions(): void
    {
        $body = $this->assertStatus(200, $this->get('/transactions'));
        $this->assertSame([], $body);
    }

    public function testReturnsAllTransactions(): void
    {
        $this->seedTransaction();
        $this->seedTransaction();

        $body = $this->assertStatus(200, $this->get('/transactions'));
        $this->assertCount(2, $body);
    }

    public function testOrdersByTimestampDescending(): void
    {
        $this->seedTransaction(['timestamp' => '2024-01-01T00:00:00+00:00']);
        $this->seedTransaction(['timestamp' => '2024-06-01T00:00:00+00:00']);

        $body = $this->assertStatus(200, $this->get('/transactions'));
        $this->assertSame('2024-06-01T00:00:00+00:00', $body[0]['timestamp']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $body[1]['timestamp']);
    }
}
