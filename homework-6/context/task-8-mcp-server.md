# Task 8 — Custom MCP server (pipeline-status)
**Date:** 2026-06-23

## Investigation / Research

### mcp/sdk version confirmed via context7

- **Library resolved:** `/modelcontextprotocol/php-sdk` (official PHP MCP SDK, High reputation, 959 snippets)
- **Installed version:** `v0.6.0` (released 2026-06-02, current latest stable)
- **Constraint in composer.json:** `^0.6.0`
- Confirmed via `composer require mcp/sdk` run inside the container.

### API approach chosen

Used the explicit builder API (`addTool()` / `addResource()`) rather than attribute-based
auto-discovery (`setDiscovery()`).  Rationale: auto-discovery requires `symfony/finder`
(an extra dependency) and scans the filesystem.  The explicit API is simpler, leaner, and
avoids that dependency.

- `Server::builder()->addTool(handler: $closure, name: '...', inputSchema: [...])` — tools
- `Server::builder()->addResource(handler: $closure, uri: '...', mimeType: '...')` — resource
- `StdioTransport` — reads JSON-RPC frames from STDIN, writes responses to STDOUT.

### stdio discipline

Nothing is written to stdout except SDK protocol frames.  The server uses `NullLogger`
(default in Builder) so no PSR-3 output leaks to stdout.  PHP errors/exceptions go to stderr.

## What was created / changed

| File                                    | Action  | Notes                                        |
|-----------------------------------------|---------|----------------------------------------------|
| `src/Mcp/PipelineStatusReader.php`      | Created | Transport-agnostic data-access class          |
| `mcp/server.php`                        | Created | MCP server runtime: tools + resource + stdio  |
| `tests/Mcp/PipelineStatusReaderTest.php`| Created | 14 unit tests, all edge cases                 |
| `composer.json`                         | Updated | Added `"mcp/sdk": "^0.6.0"`                  |
| `composer.lock`                         | Updated | Locked v0.6.0 + 20 transitive deps            |
| `.mcp.json`                             | Updated | Added `pipeline-status` stdio server entry    |
| `research-notes.md`                     | Updated | Appended Task 8 mcp/sdk decision              |

### `.mcp.json` pipeline-status launch line

```
docker run -i --rm -w /app -v ${PWD}:/app -v ${PWD}/shared:/app/shared:ro homework-6-app php mcp/server.php
```

**Rationale:**
- `-i` (not `-t`): interactive stdin, no TTY — TTY would corrupt the JSON-RPC byte stream.
- `-w /app` + `-v ${PWD}:/app`: working directory and source/vendor mount so autoload and
  `mcp/server.php` resolve correctly.
- `-v ${PWD}/shared:/app/shared:ro`: results directory read-only — MCP server only reads.
- `homework-6-app`: the pre-built project image from Task 1 (no extra MCP image needed).

## Self-verification

### Unit tests

```
make test
```

Result: **250 tests, 561 assertions — OK** (zero deprecations, no pipeline output).
14 new tests added by this task (was 236 before Task 8).

Test cases cover:
- `getTransactionStatus`: settled with fee/net, rejected with reason, unknown ID (not found),
  empty transaction_id, directory absent.
- `listPipelineResults`: mixed 3-entry results, empty directory, absent directory,
  malformed file skipped, summary.json excluded.
- `getPipelineSummary`: reads summary.txt, falls back to summary.json, placeholder when
  neither exists, placeholder when directory absent.

### Smoke test (live stdio round-trip)

Command:
```bash
printf '<line1 initialize>\n<line2 notifications/initialized>\n<line3 tools/call list_pipeline_results>\n' \
  | docker run -i --rm -w /app -v "$PWD:/app" -v "$PWD/shared:/app/shared:ro" homework-6-app php mcp/server.php
```

**Outcome: SUCCESS on first properly-mounted attempt.**

Response received (abbreviated):
```json
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-11-25","capabilities":{...},"serverInfo":{"name":"pipeline-status","version":"1.0.0",...}}}
{"jsonrpc":"2.0","id":2,"result":{"content":[{"type":"text","text":"{ \"count\": 8, \"transactions\": [...] }"}],"isError":false,...}}
```

The server listed all 8 processed transactions from `shared/results/` with correct statuses.

Note: a first attempt failed with "Could not open input file: mcp/server.php" because the
initial `.mcp.json` launch line omitted the `-v ${PWD}:/app` source mount (only shared/
was mounted).  Fixed by adding `-w /app -v ${PWD}:/app` to the launch args.
