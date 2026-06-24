<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

$server = Server::builder()
    ->setServerInfo('Lorem Server', '1.0.0')
    ->setDiscovery(__DIR__, ['src'], ['vendor'])
    ->build();

$exitCode = $server->run(new StdioTransport());

exit($exitCode);
