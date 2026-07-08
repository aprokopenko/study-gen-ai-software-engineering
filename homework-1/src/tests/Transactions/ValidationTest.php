<?php

declare(strict_types=1);

namespace Tests\Transactions;

use Tests\Concerns\AppTestCase;

class ValidationTest extends AppTestCase
{
    public function testRejectsAmountWithMoreThanTwoDecimals(): void
    {
        $this->assertValidationError($this->postJson('/transactions', [
            'toAccount' => 'ACC-00001',
            'amount'    => 100.123,
            'currency'  => 'USD',
            'type'      => 'deposit',
        ]), 'amount');
    }

    public function testAcceptsTwoDecimalAmount(): void
    {
        $r = $this->postJson('/transactions', [
            'toAccount' => 'ACC-00001',
            'amount'    => 100.12,
            'currency'  => 'USD',
            'type'      => 'deposit',
        ]);
        $this->assertSame(201, $r->getStatusCode());
    }

    public function testAcceptsWholeNumberAmount(): void
    {
        $r = $this->postJson('/transactions', [
            'toAccount' => 'ACC-00001',
            'amount'    => 100,
            'currency'  => 'USD',
            'type'      => 'deposit',
        ]);
        $this->assertSame(201, $r->getStatusCode());
    }

    public function testRejectsInvalidAccountFormat(): void
    {
        foreach (['12345', 'ACC-1', 'ACC-TOOLONG'] as $bad) {
            $this->assertValidationError($this->postJson('/transactions', [
                'fromAccount' => $bad,
                'toAccount'   => 'ACC-00002',
                'amount'      => 50.0,
                'currency'    => 'USD',
                'type'        => 'transfer',
            ]), 'fromAccount');
        }
    }

    public function testAcceptsValidAccountFormat(): void
    {
        $r = $this->postJson('/transactions', [
            'fromAccount' => 'ACC-AB123',
            'toAccount'   => 'ACC-00002',
            'amount'      => 50.0,
            'currency'    => 'USD',
            'type'        => 'transfer',
        ]);
        $this->assertSame(201, $r->getStatusCode());
    }

    public function testRejectsInvalidCurrencyCode(): void
    {
        foreach (['XXX', 'FAKE'] as $bad) {
            $this->assertValidationError($this->postJson('/transactions', [
                'toAccount' => 'ACC-00001',
                'amount'    => 50.0,
                'currency'  => $bad,
                'type'      => 'deposit',
            ]), 'currency');
        }
    }

    public function testAcceptsValidCurrencyCode(): void
    {
        foreach (['USD', 'EUR', 'JPY'] as $code) {
            $r = $this->postJson('/transactions', [
                'toAccount' => 'ACC-00001',
                'amount'    => 50.0,
                'currency'  => $code,
                'type'      => 'deposit',
            ]);
            $this->assertSame(201, $r->getStatusCode());
        }
    }

    public function testReturnsMultipleErrorsAtOnce(): void
    {
        $body = $this->assertStatus(400, $this->postJson('/transactions', [
            'fromAccount' => 'bad',
            'amount'      => -1,
            'currency'    => 'FAKE',
            'type'        => 'deposit',
        ]));
        $this->assertGreaterThanOrEqual(2, count($body['details']));
    }

    public function testDepositDoesNotRequireFromAccount(): void
    {
        $r = $this->postJson('/transactions', [
            'toAccount' => 'ACC-00001',
            'amount'    => 100.0,
            'currency'  => 'USD',
            'type'      => 'deposit',
        ]);
        $this->assertSame(201, $r->getStatusCode());
    }

    public function testWithdrawalDoesNotRequireToAccount(): void
    {
        $r = $this->postJson('/transactions', [
            'fromAccount' => 'ACC-00001',
            'amount'      => 50.0,
            'currency'    => 'USD',
            'type'        => 'withdrawal',
        ]);
        $this->assertSame(201, $r->getStatusCode());
    }

    public function testTransferRequiresBothAccounts(): void
    {
        $this->assertValidationError($this->postJson('/transactions', [
            'amount'   => 50.0,
            'currency' => 'USD',
            'type'     => 'transfer',
        ]), 'fromAccount');
    }
}
