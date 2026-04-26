<?php

declare(strict_types=1);

namespace Tests\Accounts;

use Tests\Concerns\AppTestCase;

class GetBalanceTest extends AppTestCase
{
    public function testReturnsZeroBalancesForUnknownAccount(): void
    {
        $body = $this->assertStatus(200, $this->get('/accounts/ACC-99999/balance'));
        $this->assertSame('ACC-99999', $body['accountId']);
        $this->assertSame([], $body['balances']);
    }

    public function testSumsDepositsAndWithdrawals(): void
    {
        $this->seedTransaction(['type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 200.0]);
        $this->seedTransaction(['type' => 'withdrawal', 'from_account' => 'ACC-00001', 'to_account' => null, 'amount' => 50.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/balance'));
        $this->assertSame(150.0, $body['balances'][0]['amount']);
    }

    public function testHandlesTransfersBothDirections(): void
    {
        $this->seedTransaction(['type' => 'transfer', 'from_account' => 'ACC-00001', 'to_account' => 'ACC-00002', 'amount' => 75.0]);

        $body1 = $this->assertStatus(200, $this->get('/accounts/ACC-00001/balance'));
        $body2 = $this->assertStatus(200, $this->get('/accounts/ACC-00002/balance'));

        $this->assertSame(-75.0, $body1['balances'][0]['amount']);
        $this->assertSame(75.0, $body2['balances'][0]['amount']);
    }

    public function testGroupsByCurrency(): void
    {
        $this->seedTransaction(['currency' => 'USD', 'amount' => 100.0, 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001']);
        $this->seedTransaction(['currency' => 'EUR', 'amount' => 50.0, 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001']);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/balance'));
        $currencies = array_column($body['balances'], 'currency');
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertCount(2, $body['balances']);
    }

    public function testIgnoresNonCompletedTransactions(): void
    {
        $this->seedTransaction(['status' => 'pending', 'type' => 'deposit', 'from_account' => null, 'to_account' => 'ACC-00001', 'amount' => 500.0]);

        $body = $this->assertStatus(200, $this->get('/accounts/ACC-00001/balance'));
        $this->assertSame([], $body['balances']);
    }
}
