<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Medoo\Medoo;

return function (ContainerBuilder $builder): void {
    $builder->addDefinitions([
        Medoo::class => fn () => new Medoo(config('database')),
    ]);
};
