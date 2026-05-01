<?php

declare(strict_types=1);

use App\Http\ErrorRenderer;
use App\Services\ContainerFactory;
use App\Services\Database;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/vendor/autoload.php';

$container = ContainerFactory::make();
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: (bool) getenv('APP_DEBUG'),
    logErrors: true,
    logErrorDetails: true,
);
$errorMiddleware->getDefaultErrorHandler()->registerErrorRenderer('application/json', ErrorRenderer::class);
$errorMiddleware->getDefaultErrorHandler()->setDefaultErrorRenderer('application/json', ErrorRenderer::class);

(require __DIR__ . '/app/routes.php')($app);

return $app;
