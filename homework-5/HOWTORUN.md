# How to Run the Lorem MCP Server

## Prerequisites

- Docker
- `make`

No PHP or Composer needed on the host.

## Quick start

```bash
# 1. Build the self-contained Docker image
make build

# 2. (Optional) Populate vendor/ on the host for IDE autocomplete
make install

# 3. Smoke-test the server (calls the `read` tool with wordCount=5)
make test
```

## MCP client connection

The server is registered in `.mcp.json` under the key `lorem`. After `make build`, any MCP client that reads `.mcp.json` (Claude Code, Kiro, etc.) will automatically launch the server on demand via:

```
docker run -i --rm lorem-mcp-server
```

No bind-mounts are required — the image is fully self-contained.

## Make targets

| Target        | Description |
|---------------|-------------|
| `make build`  | Build the `lorem-mcp-server` Docker image. Must be run before anything else. |
| `make install`| Run `composer install` inside the container and write `vendor/` to the local `lorem-mcp-server/` directory (for IDE support). Requires `make build`. |
| `make run`    | Start the MCP server interactively over stdio (manual sanity check). |
| `make test`   | Send an MCP `tools/call` for `read` with `wordCount=5` and print the result. |

## Resource vs Tool

| Kind     | URI / Name            | Description |
|----------|-----------------------|-------------|
| Resource | `file://lorem-ipsum.md` | A static URI that MCP clients can subscribe to and read. Returns the first N words of `lorem-ipsum.md`. |
| Tool     | `read`                | An action that MCP clients (and Claude) can call on demand. Accepts an optional `wordCount` (default 30). |

Both resolve to the same word-limited content; Resources are passive data endpoints, Tools are active callable functions.

## word_count rules

- Default: **30** words when not specified or when `wordCount <= 0`.
- Maximum: all available words in `lorem-ipsum.md` (the count is clamped, never errors).

## Note on PHP vs Python

The original Task 4 specification referenced Python + FastMCP. This implementation uses **PHP 8.4 + the official `mcp/sdk`** (`modelcontextprotocol/php-sdk`), which is the direct PHP equivalent: attribute-based capability discovery, STDIO transport, identical MCP protocol semantics. All reviewer steps use Docker only.
