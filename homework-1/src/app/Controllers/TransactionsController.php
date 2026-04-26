<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TransactionRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController extends AbstractController
{
    public function __construct(private TransactionRepository $repository) {}

    public function create(Request $request, Response $response): Response
    {
        $body = (string) $request->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->json($response, ['error' => 'Invalid JSON'], 400);
        }

        $errors = $this->validate($data);
        if (!empty($errors)) {
            return $this->json($response, ['error' => 'Validation failed', 'details' => $errors], 400);
        }

        $transaction = $this->repository->create([
            'from_account' => $data['fromAccount'] ?? null,
            'to_account'   => $data['toAccount'] ?? null,
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'type'         => $data['type'],
            'timestamp'    => gmdate('c'),
            'status'       => $data['status'] ?? 'completed',
        ]);

        return $this->json($response, $this->format($transaction), 201);
    }

    public function index(Request $request, Response $response): Response
    {
        $transactions = array_map([$this, 'format'], $this->repository->all());
        return $this->json($response, $transactions, 200);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $transaction = $this->repository->find($args['id']);

        if ($transaction === null) {
            return $this->json($response, ['error' => 'Transaction not found'], 404);
        }

        return $this->json($response, $this->format($transaction), 200);
    }

    private function validate(array $data): array
    {
        $errors = [];
        $validTypes = ['deposit', 'withdrawal', 'transfer'];

        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            $errors[] = ['field' => 'amount', 'message' => 'Amount must be a positive number'];
        }

        if (!isset($data['type']) || !in_array($data['type'], $validTypes, true)) {
            $errors[] = ['field' => 'type', 'message' => 'Type must be one of: ' . implode(', ', $validTypes)];
        }

        if (empty($data['currency'])) {
            $errors[] = ['field' => 'currency', 'message' => 'Currency is required'];
        }

        return $errors;
    }

    private function format(array $row): array
    {
        return [
            'id'          => $row['id'],
            'fromAccount' => $row['from_account'],
            'toAccount'   => $row['to_account'],
            'amount'      => (float) $row['amount'],
            'currency'    => $row['currency'],
            'type'        => $row['type'],
            'timestamp'   => $row['timestamp'],
            'status'      => $row['status'],
        ];
    }
}
