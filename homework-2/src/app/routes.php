<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use Slim\App;

return function (App $app): void {
    $app->get('/', HealthController::class);
};