<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TransactionRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountsController extends AbstractController
{
    public function __construct(private TransactionRepository $repository) {}

    public function balance(Request $request, Response $response, array $args): Response
    {
        $accountId    = $args['accountId'];
        $transactions = $this->repository->forAccount($accountId, 'completed');

        $balances = [];

        foreach ($transactions as $tx) {
            $currency = $tx['currency'];
            $amount   = (float) $tx['amount'];

            if (!isset($balances[$currency])) {
                $balances[$currency] = 0.0;
            }

            if ($tx['type'] === 'deposit' && $tx['to_account'] === $accountId) {
                $balances[$currency] += $amount;
            } elseif ($tx['type'] === 'withdrawal' && $tx['from_account'] === $accountId) {
                $balances[$currency] -= $amount;
            } elseif ($tx['type'] === 'transfer') {
                if ($tx['to_account'] === $accountId) {
                    $balances[$currency] += $amount;
                }
                if ($tx['from_account'] === $accountId) {
                    $balances[$currency] -= $amount;
                }
            }
        }

        $result = [];
        foreach ($balances as $currency => $amount) {
            $result[] = ['currency' => $currency, 'amount' => $amount];
        }

        return $this->json($response, ['accountId' => $accountId, 'balances' => $result], 200);
    }

    public function summary(Request $request, Response $response, array $args): Response
    {
        $accountId    = $args['accountId'];
        $transactions = $this->repository->forAccount($accountId, 'completed');

        $totalDeposits    = 0.0;
        $totalWithdrawals = 0.0;
        $mostRecent       = null;

        foreach ($transactions as $tx) {
            if ($tx['type'] === 'deposit' && $tx['to_account'] === $accountId) {
                $totalDeposits += (float) $tx['amount'];
            } elseif ($tx['type'] === 'withdrawal' && $tx['from_account'] === $accountId) {
                $totalWithdrawals += (float) $tx['amount'];
            }

            if ($mostRecent === null || $tx['timestamp'] > $mostRecent) {
                $mostRecent = $tx['timestamp'];
            }
        }

        return $this->json($response, [
            'accountId'             => $accountId,
            'totalDeposits'         => $totalDeposits,
            'totalWithdrawals'      => $totalWithdrawals,
            'transactionCount'      => count($transactions),
            'mostRecentTransaction' => $mostRecent,
        ], 200);
    }
}
