<?php

declare(strict_types=1);

namespace Tests\Accounts;

use Tests\Concerns\AppTestCase;

class GetSummaryTest extends AppTestCase
{
    public function testReturnsZerosForUnknownAccount(): void
    {
        $body = $this->assertStatus(200, $this->get('/accounts/ACC-99999/summary'));
        $this->assertSame('ACC-99999', $body['accountId']);
        $this->assertSame(0.0, $body['totalDeposits']);
        $this->assertSame(0.0, $body['totalWithdrawals']);
        $this->assertSame(0, $body['transactionCount']);
        $this->assertNull($body['mostRecentTransaction']);
    }

    public function testSumsDepositsCorrectly(): void
    {
        $this->seedTransaction(['type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 200.0]);
        $this->seedTransaction(['type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 300.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame(500.0, $body['totalDeposits']);
    }

    public function testSumsWithdrawalsCorrectly(): void
    {
        $this->seedTransaction(['type' => 'withdrawal', 'from_account' => 'ACC-00001', 'to_account' => null, 'amount' => 75.0]);
        $this->seedTransaction(['type' => 'withdrawal', 'from_account' => 'ACC-00001', 'to_account' => null, 'amount' => 25.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame(100.0, $body['totalWithdrawals']);
    }

    public function testCountsAllTransactionTypes(): void
    {
        $this->seedTransaction(['type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 100.0]);
        $this->seedTransaction(['type' => 'withdrawal', 'from_account' => 'ACC-00001', 'to_account' => null, 'amount' => 50.0]);
        $this->seedTransaction(['type' => 'transfer', 'from_account' => 'ACC-00001', 'to_account' => 'ACC-00002', 'amount' => 25.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame(3, $body['transactionCount']);
    }

    public function testReturnsMostRecentTransactionDate(): void
    {
        $this->seedTransaction(['timestamp' => '2024-01-01T00:00:00+00:00', 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 100.0]);
        $this->seedTransaction(['timestamp' => '2024-06-01T00:00:00+00:00', 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 50.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame('2024-06-01T00:00:00+00:00', $body['mostRecentTransaction']);
    }

    public function testIgnoresNonCompletedTransactions(): void
    {
        $this->seedTransaction(['status' => 'pending', 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 500.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame(0, $body['transactionCount']);
        $this->assertSame(0.0, $body['totalDeposits']);
    }

    public function testIncludesTransfersInCount(): void
    {
        $this->seedTransaction(['type' => 'transfer', 'from_account' => 'ACC-00001', 'to_account' => 'ACC-00002', 'amount' => 100.0]);
        $this->seedTransaction(['type' => 'transfer', 'from_account' => 'ACC-00003', 'to_account' => 'ACC-00001', 'amount' => 50.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/summary'));
        $this->assertSame(2, $body['transactionCount']);
    }
}
