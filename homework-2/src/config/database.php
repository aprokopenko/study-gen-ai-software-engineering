<?php

declare(strict_types=1);

return [
    'type'     => 'sqlite',
    'database' => getenv('DATABASE_PATH') ?: '/var/www/data/support.sqlite',
];