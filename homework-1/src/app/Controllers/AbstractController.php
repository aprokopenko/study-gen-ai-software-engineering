<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class AbstractController
{
    protected function json(Response $response, mixed $data, int $status): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRESERVE_ZERO_FRACTION));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
