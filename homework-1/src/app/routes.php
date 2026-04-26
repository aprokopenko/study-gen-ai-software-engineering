<?php

declare(strict_types=1);

use App\Controllers\AccountsController;
use App\Controllers\HomeController;
use App\Controllers\TransactionsController;
use Slim\App;

return function (App $app): void {
    $app->get('/', [HomeController::class, 'hello']);

    $app->post('/transactions', [TransactionsController::class, 'create']);
    $app->get('/transactions', [TransactionsController::class, 'index']);
    $app->get('/transactions/{id}', [TransactionsController::class, 'show']);

    $app->get('/accounts/{accountId}/balance', [AccountsController::class, 'balance']);
};
