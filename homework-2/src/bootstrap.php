<?php

declare(strict_types=1);

use App\Services\ContainerFactory;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/vendor/autoload.php';

$container = ContainerFactory::make();
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) getenv('APP_DEBUG'),
    logErrors: true,
    logErrorDetails: true,
);

(require __DIR__ . '/app/routes.php')($app);

return $app;