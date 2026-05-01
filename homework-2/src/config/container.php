<?php

declare(strict_types=1);

use App\Parsers\CsvTicketParser;
use App\Parsers\JsonTicketParser;
use App\Parsers\ParserRegistry;
use App\Parsers\XmlTicketParser;
use App\Services\Classification\ClassifierInterface;
use App\Services\Classification\NullClassifier;
use App\Services\Clock\ClockInterface;
use App\Services\Clock\SystemClock;
use App\Services\Database;
use App\Services\Ids\IdGeneratorInterface;
use App\Services\Ids\UuidGenerator;
use DI\ContainerBuilder;
use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use Somnambulist\Components\Validation\Factory as ValidationFactory;

return function (ContainerBuilder $builder): void {
    $builder->addDefinitions([
        Medoo::class => function (): Medoo {
            return new Medoo(config('database'));
        },

        Database::class => \DI\autowire(Database::class),

        ClockInterface::class       => \DI\autowire(SystemClock::class),
        IdGeneratorInterface::class => \DI\autowire(UuidGenerator::class),
        ClassifierInterface::class  => \DI\autowire(NullClassifier::class),

        ValidationFactory::class => function (): ValidationFactory {
            return new ValidationFactory();
        },

        ParserRegistry::class => function (ContainerInterface $c): ParserRegistry {
            return new ParserRegistry([
                'text/csv'         => $c->get(CsvTicketParser::class),
                'application/json' => $c->get(JsonTicketParser::class),
                'application/xml'  => $c->get(XmlTicketParser::class),
                'text/xml'         => $c->get(XmlTicketParser::class),
            ]);
        },
    ]);
};
