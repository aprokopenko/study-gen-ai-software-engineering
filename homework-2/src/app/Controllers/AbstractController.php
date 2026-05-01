<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class AbstractController
{
    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}