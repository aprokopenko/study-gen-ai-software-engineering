<?php

declare(strict_types=1);

use App\Http\ErrorHandler;
use App\Http\ErrorRenderer;
use App\Services\ContainerFactory;
use App\Services\Database;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/vendor/autoload.php';

$container = ContainerFactory::make();
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$debug = (bool) getenv('APP_DEBUG');

$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: $debug,
    logErrors: $debug,
    logErrorDetails: $debug,
);

$errorHandler = new ErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
);
$errorHandler->registerErrorRenderer('application/json', ErrorRenderer::class);
$errorHandler->setDefaultErrorRenderer('application/json', ErrorRenderer::class);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

(require __DIR__ . '/app/routes.php')($app);

return $app;
