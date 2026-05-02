<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filters\TicketFilter;
use App\Services\ImportService;
use App\Services\TicketNotFoundException;
use App\Services\TicketService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class TicketsController extends AbstractController
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly ImportService $importService,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $tickets = $this->ticketService->list(TicketFilter::fromParams($request->getQueryParams()));

        return $this->json($response, array_map(fn($t) => $t->jsonSerialize(), $tickets));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $ticket = $this->ticketService->findOrFail($args['id']);
        } catch (TicketNotFoundException) {
            throw new HttpNotFoundException($request);
        }

        return $this->json($response, $ticket->jsonSerialize());
    }

    public function create(Request $request, Response $response): Response
    {
        $autoClassify = filter_var($request->getQueryParams()['auto_classify'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ticket = $this->ticketService->create((array) $request->getParsedBody(), $autoClassify);

        return $this->json($response, $ticket->jsonSerialize(), 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $ticket = $this->ticketService->findOrFail($args['id']);
        } catch (TicketNotFoundException) {
            throw new HttpNotFoundException($request);
        }

        $updated = $this->ticketService->update($ticket, (array) $request->getParsedBody());

        return $this->json($response, $updated->jsonSerialize());
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $ticket = $this->ticketService->findOrFail($args['id']);
        } catch (TicketNotFoundException) {
            throw new HttpNotFoundException($request);
        }

        $this->ticketService->delete($ticket);

        return $response->withStatus(204);
    }

    public function autoClassify(Request $request, Response $response, array $args): Response
    {
        try {
            $ticket = $this->ticketService->findOrFail($args['id']);
        } catch (TicketNotFoundException) {
            throw new HttpNotFoundException($request);
        }

        $result = $this->ticketService->autoClassify($ticket);

        return $this->json($response, [
            'category' => $result->suggestedCategory->value,
            'priority' => $result->suggestedPriority->value,
            'confidence' => $result->confidence,
            'reasoning' => $result->reasoning,
            'keywords' => $result->keywords,
        ]);
    }

    public function import(Request $request, Response $response): Response
    {
        $summary = $this->importService->import(
            raw:         (string) $request->getBody(),
            contentType: $request->getHeaderLine('Content-Type'),
            format:      $request->getQueryParams()['format'] ?? null,
        );

        return $this->json($response, $summary->toArray());
    }
}
