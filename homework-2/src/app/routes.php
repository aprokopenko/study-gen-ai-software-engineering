<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\TicketsController;
use Slim\App;

return function (App $app): void {
    $app->get('/', HealthController::class);

    // Import must be before /:id to avoid route conflict
    $app->post('/tickets/import', [TicketsController::class, 'import']);

    $app->get('/tickets',          [TicketsController::class, 'index']);
    $app->post('/tickets',         [TicketsController::class, 'create']);
    $app->get('/tickets/{id}',     [TicketsController::class, 'show']);
    $app->put('/tickets/{id}',     [TicketsController::class, 'update']);
    $app->delete('/tickets/{id}',  [TicketsController::class, 'delete']);
};
