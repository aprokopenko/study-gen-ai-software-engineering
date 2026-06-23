<?php

/**
 * pipeline-status MCP server.
 *
 * Launched on demand by the MCP client (Claude Code / any MCP host) via a
 * one-shot Docker container over stdio.  Uses the official mcp/sdk with
 * StdioTransport.
 *
 * ## stdio discipline
 * NOTHING is written to stdout except JSON-RPC protocol frames produced by the
 * SDK.  All diagnostic output goes to stderr.  Never add echo/print here or in
 * any class this file requires.
 *
 * ## Launch command (recorded in .mcp.json)
 *   docker run -i --rm \
 *     -v "$PWD/shared:/app/shared:ro" \
 *     homework-6-app \
 *     php mcp/server.php
 *
 * ## Tools exposed
 *   - get_transaction_status(transaction_id: string)
 *   - list_pipeline_results()
 *
 * ## Resources exposed
 *   - pipeline://summary  → latest run summary text
 */

declare(strict_types=1);

// ── bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../vendor/autoload.php';

use BankingPipeline\Mcp\PipelineStatusReader;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

// Resolve the shared/results directory.
// Inside the container /app is the working directory (see docker-compose.yml).
// shared/ is bind-mounted at /app/shared (read-only for the MCP server).
$resultsDir = __DIR__ . '/../shared/results';

$reader = new PipelineStatusReader($resultsDir);

// ── server wiring ─────────────────────────────────────────────────────────────

$server = Server::builder()
    ->setServerInfo(
        name: 'pipeline-status',
        version: '1.0.0',
        description: 'Query the banking transaction-processing pipeline results',
    )
    // ── Tool: get_transaction_status ─────────────────────────────────────────
    ->addTool(
        handler: static function (string $transaction_id) use ($reader): array {
            return $reader->getTransactionStatus($transaction_id);
        },
        name: 'get_transaction_status',
        description: 'Get the current status of a single transaction by its ID. '
            . 'Returns status (settled/rejected), fee/net for settled transactions, '
            . 'or rejection reason for rejected ones. Returns a "not found" indicator '
            . 'for unknown transaction IDs.',
        inputSchema: [
            'type'       => 'object',
            'properties' => [
                'transaction_id' => [
                    'type'        => 'string',
                    'description' => 'The transaction ID to look up (e.g. "TXN001")',
                ],
            ],
            'required' => ['transaction_id'],
        ],
    )
    // ── Tool: list_pipeline_results ──────────────────────────────────────────
    ->addTool(
        handler: static function () use ($reader): array {
            return $reader->listPipelineResults();
        },
        name: 'list_pipeline_results',
        description: 'List all processed transactions from the last pipeline run. '
            . 'Returns a count and an array of {transaction_id, status} objects. '
            . 'Returns an empty list if no run has been performed yet.',
    )
    // ── Resource: pipeline://summary ─────────────────────────────────────────
    ->addResource(
        handler: static function () use ($reader): string {
            return $reader->getPipelineSummary();
        },
        uri: 'pipeline://summary',
        name: 'pipeline-summary',
        description: 'The latest run summary: total processed, settled, rejected, and rejection breakdown.',
        mimeType: 'text/plain',
    )
    ->build();

// ── run over stdio ────────────────────────────────────────────────────────────
// StdioTransport reads JSON-RPC frames from STDIN and writes responses to STDOUT.
// Any stray stdout (echo/print) before or after this call would break the stream.

$transport = new StdioTransport();
$exitCode  = $server->run($transport);
exit(is_int($exitCode) ? $exitCode : 0);
