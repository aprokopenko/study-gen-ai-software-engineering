<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Services\Database;

container(Database::class)->migrate(__DIR__ . '/../database/schema.sql');

echo "Setup complete.\n";
