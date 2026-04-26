<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Repositories\TransactionRepository;
use App\Services\ContainerFactory;
use App\Services\Database;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class AppTestCase extends TestCase
{
    protected App $app;
    protected TransactionRepository $transactions;

    protected function setUp(): void
    {
        putenv('DATABASE_PATH=:memory:');
        ContainerFactory::reset();
        $this->app = require __DIR__ . '/../../bootstrap.php';
        container(Database::class)->migrate(__DIR__ . '/../../database/schema.sql');
        $this->transactions = container(TransactionRepository::class);
    }

    protected function get(string $uri): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);
        return $this->app->handle($request);
    }

    protected function postJson(string $uri, array $body): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode($body));
        return $this->app->handle($request);
    }

    protected function postRaw(string $uri, string $body): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write($body);
        return $this->app->handle($request);
    }

    protected function decode(ResponseInterface $r): array
    {
        return json_decode((string) $r->getBody(), true);
    }

    protected function assertStatus(int $expected, ResponseInterface $r): array
    {
        $this->assertSame($expected, $r->getStatusCode());
        return $this->decode($r);
    }

    protected function seedTransaction(array $overrides = []): array
    {
        return $this->transactions->create(array_merge([
            'from_account' => 'ACC-001',
            'to_account'   => 'ACC-002',
            'amount'       => 100.00,
            'currency'     => 'USD',
            'type'         => 'transfer',
            'timestamp'    => gmdate('c'),
            'status'       => 'completed',
        ], $overrides));
    }

    protected function assertValidationError(ResponseInterface $r, string $field): void
    {
        $body = $this->assertStatus(400, $r);
        $this->assertSame('Validation failed', $body['error']);
        $fields = array_column($body['details'], 'field');
        $this->assertContains($field, $fields);
    }
}
