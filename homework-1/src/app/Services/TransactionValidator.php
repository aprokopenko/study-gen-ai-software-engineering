<?php

declare(strict_types=1);

namespace App\Services;

use Alcohol\ISO4217;

class TransactionValidator
{
    private const ACCOUNT_PATTERN = '/^ACC-[A-Za-z0-9]{5}$/';
    private const VALID_TYPES = ['deposit', 'withdrawal', 'transfer'];

    public function validate(array $data): array
    {
        $errors = [];

        // Amount: positive, max 2 decimal places
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            $errors[] = ['field' => 'amount', 'message' => 'Amount must be a positive number'];
        } elseif (preg_match('/\.(\d{3,})$/', (string) $data['amount'])) {
            $errors[] = ['field' => 'amount', 'message' => 'Amount must have at most 2 decimal places'];
        }

        // Type
        if (!isset($data['type']) || !in_array($data['type'], self::VALID_TYPES, true)) {
            $errors[] = ['field' => 'type', 'message' => 'Type must be one of: ' . implode(', ', self::VALID_TYPES)];
        }

        // Currency: ISO 4217
        if (empty($data['currency'])) {
            $errors[] = ['field' => 'currency', 'message' => 'Currency is required'];
        } else {
            try {
                (new ISO4217())->getByAlpha3(strtoupper((string) $data['currency']));
            } catch (\OutOfBoundsException|\DomainException) {
                $errors[] = ['field' => 'currency', 'message' => 'Invalid currency code'];
            }
        }

        // Account format: ACC-XXXXX (5 alphanumeric chars)
        $type = $data['type'] ?? null;

        if ($type !== 'deposit' && isset($data['fromAccount'])) {
            if (!preg_match(self::ACCOUNT_PATTERN, (string) $data['fromAccount'])) {
                $errors[] = ['field' => 'fromAccount', 'message' => 'Account must match format ACC-XXXXX'];
            }
        } elseif ($type === 'transfer' && !isset($data['fromAccount'])) {
            $errors[] = ['field' => 'fromAccount', 'message' => 'fromAccount is required for transfers'];
        }

        if ($type !== 'withdrawal' && isset($data['toAccount'])) {
            if (!preg_match(self::ACCOUNT_PATTERN, (string) $data['toAccount'])) {
                $errors[] = ['field' => 'toAccount', 'message' => 'Account must match format ACC-XXXXX'];
            }
        } elseif ($type === 'transfer' && !isset($data['toAccount'])) {
            $errors[] = ['field' => 'toAccount', 'message' => 'toAccount is required for transfers'];
        }

        return $errors;
    }
}
