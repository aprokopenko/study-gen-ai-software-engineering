<?php

declare(strict_types=1);

namespace Tests\Transactions;

use Tests\Concerns\AppTestCase;

class FilterTransactionsTest extends AppTestCase
{
    public function testFiltersByAccountId(): void
    {
        $this->seedTransaction(['from_account' => 'ACC-00001', 'to_account' => 'ACC-00002']);
        $this->seedTransaction(['from_account' => 'ACC-00003', 'to_account' => 'ACC-00004']);

        $body = $this->assertStatus(200, $this->get('/transactions?accountId=ACC-00001'));
        $this->assertCount(1, $body);
    }

    public function testFiltersByType(): void
    {
        $this->seedTransaction(['type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001']);
        $this->seedTransaction(['type' => 'transfer']);

        $body = $this->assertStatus(200, $this->get('/transactions?type=deposit'));
        $this->assertCount(1, $body);
        $this->assertSame('deposit', $body[0]['type']);
    }

    public function testFiltersByDateRange(): void
    {
        $this->seedTransaction(['timestamp' => '2024-03-01T00:00:00+00:00']);
        $this->seedTransaction(['timestamp' => '2024-07-01T00:00:00+00:00']);

        $body = $this->assertStatus(200, $this->get('/transactions?from=2024-01-01&to=2024-06-30'));
        $this->assertCount(1, $body);
        $this->assertSame('2024-03-01T00:00:00+00:00', $body[0]['timestamp']);
    }

    public function testFiltersByFromDateOnly(): void
    {
        $this->seedTransaction(['timestamp' => '2024-01-01T00:00:00+00:00']);
        $this->seedTransaction(['timestamp' => '2024-06-01T00:00:00+00:00']);

        $body = $this->assertStatus(200, $this->get('/transactions?from=2024-04-01'));
        $this->assertCount(1, $body);
        $this->assertSame('2024-06-01T00:00:00+00:00', $body[0]['timestamp']);
    }

    public function testFiltersByToDateOnly(): void
    {
        $this->seedTransaction(['timestamp' => '2024-01-01T00:00:00+00:00']);
        $this->seedTransaction(['timestamp' => '2024-06-01T00:00:00+00:00']);

        $body = $this->assertStatus(200, $this->get('/transactions?to=2024-03-01'));
        $this->assertCount(1, $body);
        $this->assertSame('2024-01-01T00:00:00+00:00', $body[0]['timestamp']);
    }

    public function testCombinesMultipleFilters(): void
    {
        $this->seedTransaction(['from_account' => 'ACC-00001', 'to_account' => 'ACC-00002', 'type' => 'transfer']);
        $this->seedTransaction(['from_account' => 'ACC-00001', 'to_account' => null, 'type' => 'withdrawal']);
        $this->seedTransaction(['from_account' => 'ACC-00003', 'to_account' => 'ACC-00004', 'type' => 'transfer']);

        $body = $this->assertStatus(200, $this->get('/transactions?accountId=ACC-00001&type=transfer'));
        $this->assertCount(1, $body);
        $this->assertSame('transfer', $body[0]['type']);
    }

    public function testReturnsEmptyArrayWhenNoMatch(): void
    {
        $this->seedTransaction();
        $body = $this->assertStatus(200, $this->get('/transactions?accountId=ACC-99999'));
        $this->assertSame([], $body);
    }

    public function testReturnsAllWhenNoFilters(): void
    {
        $this->seedTransaction();
        $this->seedTransaction();
        $body = $this->assertStatus(200, $this->get('/transactions'));
        $this->assertCount(2, $body);
    }
}
