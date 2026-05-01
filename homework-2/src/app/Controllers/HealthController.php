<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController extends AbstractController
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Support Ticket API',
        ]);
    }
}