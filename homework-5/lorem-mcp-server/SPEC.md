# Custom MCP Server (PHP / official `mcp/sdk`) Specification

> Ingest the information from this file, implement the Low-Level Tasks, and generate the code that will satisfy the High and Mid-Level Objectives.

## High-Level Objective

- Build a custom MCP server in **PHP** (using the official `mcp/sdk`) that exposes a lorem-ipsum text source as an MCP **Resource** and a `read` **Tool**, fully runnable through Docker and `make`, with no PHP, Composer, or dependencies installed on the host.

## Mid-Level Objectives

- Deliver a working custom MCP server that Claude/Copilot can connect to and call successfully.
- Expose the contents of a lorem-ipsum text file as a readable Resource that returns a limited number of words.
- Let the caller choose how many words come back, and return a sensible default amount when they do not say.
- Offer a `read` action (Tool) that returns the same word-limited content on demand.
- Make the server start with a single, documented command that anyone can reproduce.
- Run the entire server and its setup inside Docker so the reviewer needs nothing on their machine except Docker and `make`.
- Provide simple `make` shortcuts for installing dependencies, starting the server, and testing the `read` action.
- Keep downloaded dependencies out of version control so the repository stays clean.

## Implementation Notes

- **SDK**: Use the official PHP SDK — Composer package `mcp/sdk` (repo `modelcontextprotocol/php-sdk`), maintained by The PHP Foundation + Symfony. This is the PHP equivalent of FastMCP. List it explicitly in `composer.json` `require`. Attribute discovery also pulls in `symfony/finder`, so require that too.
- **PHP version**: Target PHP 8.4+ (attributes are required by the SDK).
- **Transport**: Use the **STDIO** transport (`Mcp\Server\Transport\StdioTransport`) — the MCP client launches the process and speaks over stdin/stdout. Do not print anything to stdout except the protocol stream (no `echo`/debug output; send any logging to stderr).
- **Capabilities via attributes**:
  - `#[McpResource(uri: 'file://lorem-ipsum.md', name: 'lorem_ipsum', mimeType: 'text/plain')]` on a method that reads `lorem-ipsum.md` and returns the first `wordCount` words.
  - `#[McpTool(name: 'read')]` on a method that accepts an optional `wordCount` and returns the same word-limited content (delegating to the resource method).
  - `wordCount` is an optional integer, **default `30`**. Clamp values larger than the available word count to all words; treat non-positive values (`<= 0`) as the default `30`.
  - "Words" = whitespace-separated tokens (`preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY)`), joined back with single spaces.
- **Discovery**: Bootstrap with `Server::builder()->setServerInfo('Lorem Server', '1.0.0')->setDiscovery(__DIR__, ['src'], ['vendor'])->build()` (scan base dir, include `src`, exclude `vendor`) and run on the stdio transport from `server.php`, returning the run exit code via `exit()`.
- **Docker**: Provide a `Dockerfile` based on an official `php:8.4-cli` image. Install Composer inside the image, run `composer install`, and **COPY `server.php`, `lorem-ipsum.md`, and `src/` into the image** so it is fully self-contained. The container's entrypoint runs `php server.php`. The MCP client invokes the server via `docker run -i --rm …` (matching the `-i` pattern already used by the filesystem/github servers in `.mcp.json`). Add a `.dockerignore` excluding `vendor/`.
- **Make targets** (run everything through Docker — never PHP/Composer on the host):
  - `make build` — build the Docker image.
  - `make install` — (depends on `build`) run `composer install` inside the container with `lorem-mcp-server/` mounted, writing `vendor/` to the host for IDE autocomplete only (the image does not need it).
  - `make run` — start the MCP server over stdio (mainly for manual sanity checks).
  - `make test` — pipe an `initialize` + `tools/call read` JSON-RPC sequence into the server and show the output.
- **Dependency hygiene**: Add a `lorem-mcp-server/.gitignore` that excludes `/vendor/` and `composer.lock` is **committed** (lock the dependency set). Do not commit `vendor/`.
- **MCP config**: Register the server in the project `.mcp.json` (and mirror to `.kiro/settings/mcp.json` if that client is used) with a `docker run -i --rm lorem-mcp-server` command. No bind-mount is needed — the image bakes in `lorem-ipsum.md`.
- **Coding standards**: PSR-12, typed signatures, one capability class under `src/`.
- **Constraint reminder**: This deviates from the original Task 4 wording (Python `server.py` + `fastmcp`). The PHP substitution is intentional and must be called out in the docs so the reviewer understands the equivalence.

## Context

### Beginning context

- `homework-5/TASKS.md` — original Task 4 requirements (Python/FastMCP).
- `homework-5/.mcp.json` — existing MCP servers (filesystem, github, notion, context7), Docker-based.
- `homework-5/lorem-mcp-server/SPEC.md` — this file.
- No PHP, Composer, or `vendor/` present; Docker + `make` available on host.

### Ending context

- `homework-5/lorem-mcp-server/`
  - `server.php` — bootstraps and runs the MCP server over stdio.
  - `src/LoremCapabilities.php` — Resource + `read` Tool, attribute-annotated.
  - `lorem-ipsum.md` — source text for the Resource/Tool.
  - `composer.json` — requires `mcp/sdk` + `symfony/finder`; `composer.lock` committed.
  - `Dockerfile` — PHP 8.4 CLI image with Composer; self-contained (bakes in sources + `lorem-ipsum.md`).
  - `.dockerignore` — excludes `vendor/` from the build context.
  - `.gitignore` — excludes `/vendor/`.
  - `SPEC.md` — this file.
- `homework-5/Makefile` — `build`, `install`, `run`, `test` targets (all via Docker), run directly from the project dir.
- `homework-5/HOWTORUN.md` — install, run, connect, and test instructions.
- `homework-5/.mcp.json` — updated with a `lorem` server entry (Docker command).
- `vendor/` exists locally but is git-ignored.
- A captured screenshot of a successful `read` Tool call in `docs/screenshots/`.

## Low-Level Tasks

### 1. Scaffold the server folder and dependencies

- Files: `lorem-mcp-server/composer.json`, `lorem-mcp-server/.gitignore`, `lorem-mcp-server/lorem-ipsum.md`.
- `composer.json` requires `php >=8.4`, `mcp/sdk`, and `symfony/finder`, with PSR-4 autoload `App\\` → `src/`.
- `.gitignore` excludes `/vendor/`.
- `lorem-ipsum.md` holds ≥60 whitespace-separated words (currently 100) so `wordCount` defaults and large values are demonstrable.

### 2. Implement the Resource and `read` Tool

- File: `lorem-mcp-server/src/LoremCapabilities.php` (namespace `App`).
- Methods: `loremResource(int $wordCount = 30)`, `read(int $wordCount = 30)` (delegates to `loremResource`), private `firstWords(int $n)`.
- Read `lorem-ipsum.md` relative to the class, split on whitespace, slice to `wordCount`, join with single spaces.
- Clamp `wordCount` to the available count; treat `<= 0` as the default `30`.
- Annotate with `#[McpResource(uri: 'file://lorem-ipsum.md', name: 'lorem_ipsum', mimeType: 'text/plain')]` and `#[McpTool(name: 'read')]`; keep all output off stdout except via the SDK.

### 3. Bootstrap the server entrypoint

- File: `lorem-mcp-server/server.php` (top-level bootstrap, no class).
- `require vendor/autoload.php`, then `Server::builder()->setServerInfo('Lorem Server','1.0.0')->setDiscovery(__DIR__, ['src'], ['vendor'])->build()`.
- Run on stdio via `$exitCode = $server->run(new StdioTransport()); exit($exitCode);` with no stray stdout output.

### 4. Containerize and add make targets

- Files: `lorem-mcp-server/Dockerfile`, `lorem-mcp-server/.dockerignore`, `homework-5/Makefile` (project root, so targets run directly from the project dir).
- Dockerfile: `php:8.4-cli` base, install Composer, `composer install`, COPY `server.php` + `lorem-ipsum.md` + `src/` into the image (self-contained), entrypoint `php server.php`. `.dockerignore` excludes `vendor/`.
- Makefile: `build` → `docker build` against `lorem-mcp-server/`; `install` (depends on `build`) → `docker run` `composer install` with `lorem-mcp-server/` mounted, writing host `vendor/` for IDE autocomplete; `run` → `docker run -i --rm` the server; `test` → pipe an `initialize` + `tools/call read` (`wordCount=5`) JSON-RPC sequence into the server and print the result.
- All targets run in Docker — never PHP/Composer on the host.

### 5. Register MCP config and document

- Files: `homework-5/.mcp.json`, `homework-5/.kiro/settings/mcp.json` (if used), `homework-5/HOWTORUN.md` (project root, alongside the Makefile).
- `.mcp.json` `lorem` entry mirrors the existing Docker servers' `-i --rm` style: `docker run -i --rm lorem-mcp-server` (no bind-mount — the image is self-contained).
- `HOWTORUN.md` covers `make build`, `make install`, `make run`/`make test`, the MCP config snippet, the Resource-vs-Tool explanation (Resources are URIs Claude reads; Tools are actions Claude calls), and a note that this PHP build replaces the spec's Python/FastMCP version.
