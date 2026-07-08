<?php

declare(strict_types=1);

namespace Tests;

use Tests\Concerns\AppTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class HomeTest extends AppTestCase
{
    public function testHelloReturnsOk(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Hello, World!', $body['message']);
    }
}
