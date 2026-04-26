<?php

declare(strict_types=1);

namespace Tests\Transactions;

use Tests\Concerns\AppTestCase;

class CreateTransactionTest extends AppTestCase
{
    public function testCreatesTransactionAndReturns201(): void
    {
        $body = $this->assertStatus(201, $this->postJson('/transactions', [
            'fromAccount' => 'ACC-001',
            'toAccount'   => 'ACC-002',
            'amount'      => 50.0,
            'currency'    => 'USD',
            'type'        => 'transfer',
        ]));

        $this->assertSame(50.0, $body['amount']);
        $this->assertSame('transfer', $body['type']);
        $this->assertSame('completed', $body['status']);
    }

    public function testGeneratesIdAndTimestampWhenMissing(): void
    {
        $body = $this->assertStatus(201, $this->postJson('/transactions', [
            'toAccount' => 'ACC-001',
            'amount'    => 100.0,
            'currency'  => 'EUR',
            'type'      => 'deposit',
        ]));

        $this->assertNotEmpty($body['id']);
        $this->assertNotEmpty($body['timestamp']);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->assertValidationError($this->postJson('/transactions', [
            'amount'   => -10.0,
            'currency' => 'USD',
            'type'     => 'deposit',
        ]), 'amount');
    }

    public function testRejectsZeroAmount(): void
    {
        $this->assertValidationError($this->postJson('/transactions', [
            'amount'   => 0,
            'currency' => 'USD',
            'type'     => 'deposit',
        ]), 'amount');
    }

    public function testRejectsInvalidJson(): void
    {
        $response = $this->postRaw('/transactions', '{invalid json}');
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRejectsUnknownType(): void
    {
        $this->assertValidationError($this->postJson('/transactions', [
            'amount'   => 50.0,
            'currency' => 'USD',
            'type'     => 'unknown',
        ]), 'type');
    }
}
