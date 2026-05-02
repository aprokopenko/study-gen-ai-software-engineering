<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\ErrorRenderer;
use App\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;

class ErrorRendererTest extends TestCase
{
    public function test_renders_validation_exception_as_json(): void
    {
        $exception = new ValidationException([
            'customer_email' => 'The Customer Email is not valid email',
            'subject' => 'The Subject is required',
        ]);

        $renderer = new ErrorRenderer();
        $json = $renderer($exception, false);
        $result = json_decode($json, true);

        $this->assertEquals('Validation failed', $result['error']);
        $this->assertIsArray($result['details']);
        $this->assertEquals('The Customer Email is not valid email', $result['details']['customer_email']);
        $this->assertEquals('The Subject is required', $result['details']['subject']);
    }

    public function test_renders_http_not_found_exception_as_json(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $exception = new HttpNotFoundException($request);

        $renderer = new ErrorRenderer();
        $json = $renderer($exception, false);
        $result = json_decode($json, true);

        $this->assertEquals('Not found', $result['error']);
    }

    public function test_renders_generic_throwable_as_internal_server_error(): void
    {
        $exception = new \RuntimeException('Something went wrong');

        $renderer = new ErrorRenderer();
        $json = $renderer($exception, false);
        $result = json_decode($json, true);

        $this->assertEquals('Internal server error', $result['error']);
    }
}
