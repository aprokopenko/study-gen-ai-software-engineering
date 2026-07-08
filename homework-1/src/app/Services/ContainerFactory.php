<?php

declare(strict_types=1);

namespace App\Services;

use DI\Container;
use DI\ContainerBuilder;

class ContainerFactory
{
    private static ?Container $container = null;

    public static function reset(): void
    {
        self::$container = null;
    }

    public static function get(): Container
    {
        if (self::$container === null) {
            $builder = new ContainerBuilder();
            (require __DIR__ . '/../../config/container.php')($builder);
            self::$container = $builder->build();
        }

        return self::$container;
    }
}
