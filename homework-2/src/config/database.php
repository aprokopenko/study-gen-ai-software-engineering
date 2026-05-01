<?php

declare(strict_types=1);

return [
    'database_type' => 'sqlite',
    'database_file' => getenv('DATABASE_PATH') ?: '/var/www/data/support.sqlite',
];