<?php

declare(strict_types=1);

namespace App\Services;

use DI\Container;
use DI\ContainerBuilder;

class ContainerFactory
{
    private static ?Container $instance = null;

    public static function make(): Container
    {
        if (self::$instance === null) {
            $builder = new ContainerBuilder();
            $builder->addDefinitions(require __DIR__ . '/../../config/container.php');
            self::$instance = $builder->build();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}