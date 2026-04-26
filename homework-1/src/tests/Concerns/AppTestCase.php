<?php

declare(strict_types=1);

namespace Tests\Concerns;

use PHPUnit\Framework\TestCase;
use Slim\App;

abstract class AppTestCase extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../bootstrap.php';
    }
}
