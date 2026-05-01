<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\ContainerFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class AppTestCase extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('DATABASE_PATH=:memory:');
        ContainerFactory::reset();
        $this->app = require __DIR__ . '/../../bootstrap.php';
    }

    protected function get(string $path): ResponseInterface
    {
        return $this->app->handle($this->createRequest('GET', $path));
    }

    protected function postJson(string $path, array $data): ResponseInterface
    {
        $body = (new StreamFactory())->createStream((string) json_encode($data));
        $request = $this->createRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        return $this->app->handle($request);
    }

    protected function postRaw(string $path, string $body, string $contentType): ResponseInterface
    {
        $stream = (new StreamFactory())->createStream($body);
        $request = $this->createRequest('POST', $path)
            ->withHeader('Content-Type', $contentType)
            ->withBody($stream);

        return $this->app->handle($request);
    }

    private function createRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $uri);
    }
}