<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ContainerFactory;
use App\Services\Database;

$container = ContainerFactory::make();
$db = $container->get(Database::class);
$db->migrate(__DIR__ . '/../database/schema.sql');

echo 'Database setup complete.' . PHP_EOL;
