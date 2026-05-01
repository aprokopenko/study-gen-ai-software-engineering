<?php

declare(strict_types=1);

use App\Services\Database;
use Medoo\Medoo;

return [
    Medoo::class => function (): Medoo {
        return new Medoo(config('database'));
    },

    Database::class => \DI\autowire(Database::class),
];