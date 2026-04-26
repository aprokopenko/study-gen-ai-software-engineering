<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database;
use Ramsey\Uuid\Uuid;

class TransactionRepository
{
    public function __construct(private Database $db) {}

    public function create(array $data): array
    {
        $data['id'] = Uuid::uuid4()->toString();
        $this->db->query()->insert('transactions', $data);
        return $this->find($data['id']);
    }

    public function all(): array
    {
        return $this->db->query()->select('transactions', '*', ['ORDER' => ['timestamp' => 'DESC']]) ?: [];
    }

    public function find(string $id): ?array
    {
        $result = $this->db->query()->get('transactions', '*', ['id' => $id]);
        return $result ?: null;
    }

    public function forAccount(string $accountId, string $status = 'completed'): array
    {
        return $this->db->query()->select('transactions', '*', [
            'AND' => [
                'OR' => [
                    'from_account' => $accountId,
                    'to_account'   => $accountId,
                ],
                'status' => $status,
            ],
        ]) ?: [];
    }
}
