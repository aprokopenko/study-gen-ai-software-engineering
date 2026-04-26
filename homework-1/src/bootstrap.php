<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Services\ContainerFactory;
use Slim\Factory\AppFactory;

$container = ContainerFactory::get();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

(require __DIR__ . '/app/routes.php')($app);

return $app;
