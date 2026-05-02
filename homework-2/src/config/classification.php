<?php

declare(strict_types=1);

return [
    'categories' => [
        'account_access' => ['login', 'password', '2fa', 'sign in', 'locked out'],
        'technical_issue' => ['error', 'crash', 'freeze', 'broken'],
        'billing_question' => ['invoice', 'payment', 'refund', 'charge', 'billing'],
        'feature_request' => ['feature', 'suggestion', 'would be nice', 'please add'],
        'bug_report' => ['bug', 'defect', 'reproduce', 'steps to reproduce', 'failure'],
    ],
    'priorities' => [
        'urgent' => ["can't access", 'critical', 'production down', 'security'],
        'high' => ['important', 'blocking', 'asap'],
        'low' => ['minor', 'cosmetic', 'suggestion'],
    ],
];