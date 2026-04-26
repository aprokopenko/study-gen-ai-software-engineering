<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Services\ContainerFactory;
use Medoo\Medoo;

$db = ContainerFactory::get()->get(Medoo::class);

// TODO: run migrations here, e.g.:
// $db->exec("CREATE TABLE IF NOT EXISTS transactions (...)");

echo "Setup complete.\n";
