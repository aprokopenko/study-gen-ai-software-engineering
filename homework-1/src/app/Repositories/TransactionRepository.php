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

    public function filter(array $filters = []): array
    {
        $where = ['ORDER' => ['timestamp' => 'DESC']];

        if (!empty($filters['accountId'])) {
            $where['OR'] = [
                'from_account' => $filters['accountId'],
                'to_account'   => $filters['accountId'],
            ];
        }

        if (!empty($filters['type'])) {
            $where['type'] = $filters['type'];
        }

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $where['timestamp[<>]'] = [$filters['from'], $filters['to']];
        } elseif (!empty($filters['from'])) {
            $where['timestamp[>=]'] = $filters['from'];
        } elseif (!empty($filters['to'])) {
            $where['timestamp[<=]'] = $filters['to'];
        }

        return $this->db->query()->select('transactions', '*', $where) ?: [];
    }

    public function all(): array
    {
        return $this->filter();
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
