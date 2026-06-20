# Homework 5: MCP Servers Configuration — Progress Report

> Student: Oleksandr Prokopenko
> Date: 2026-06-19
> Tools used: Claude CLI/VS plugin

Configure three external MCP servers (GitHub, Filesystem, Notion) and build one custom MCP server. The custom server is implemented in **PHP** with the official [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) (the PHP equivalent of FastMCP) instead of Python — see [lorem-mcp-server/SPEC.md](./lorem-mcp-server/SPEC.md). See [TASKS.md](./TASKS.md) for full requirements.

## Quick Start

```bash
# 1. Build the custom MCP server image (required before the `lorem` server can launch)
make build

# 2. Provide local Claude settings with your GitHub token
cp .claude/settings.local.example.json .claude/settings.local.json
#    then edit .claude/settings.local.json and replace YOUR_TOKEN_HERE
#    with a GitHub PAT (settings.local.json is git-ignored)

# 3. (Optional) smoke-test the custom server
make test
```

Reload your MCP client (Claude Code) so it picks up [.mcp.json](./.mcp.json). All five servers then launch on demand. For custom-server details (install, run, connect, test) see [HOWTORUN.md](./HOWTORUN.md).

## Configured Servers (`.mcp.json`)

| Server | Transport | Status |
|--------|-----------|--------|
| GitHub | Docker (`ghcr.io/github/github-mcp-server`) | Configured |
| Filesystem | Docker (`mcp/filesystem`) | Configured |
| Notion | HTTP (`https://mcp.notion.com/mcp`) | Configured |
| Custom `lorem` (PHP `mcp/sdk`) | Docker (`lorem-mcp-server`) | Configured |

---

## Implementation Notes

### Task 1 — GitHub MCP

Configured via the official Docker image `ghcr.io/github/github-mcp-server`, authenticated with `GITHUB_PERSONAL_ACCESS_TOKEN`. The token is supplied through `.claude/settings.local.json` (git-ignored); see [Quick Start](#quick-start).

### Task 2 — Filesystem MCP

#### Problem: server scoped to the wrong directory

The filesystem MCP was originally launched via `npx` with the parent directory as an argument:

```json
"filesystem": {
  "command": "npx",
  "args": ["-y", "@modelcontextprotocol/server-filesystem", "/home/alex/Sites/study/homework"]
}
```

Despite the `/home/alex/Sites/study/homework` argument, `list_allowed_directories` only ever returned `/home/alex/Sites/study/homework/homework-5`, so searches across sibling `homework-*` dirs failed with *"Access denied - path outside allowed directories."*

#### Root cause: MCP "roots" protocol overrides the CLI argument

`@modelcontextprotocol/server-filesystem` (v2026.1.14) implements the MCP **roots** protocol. When the client declares a `roots` capability, the server requests the client's roots on init and **replaces** its command-line directories with them:

- `index.js` → `updateAllowedDirectoriesFromRoots()` does `allowedDirectories = [...validatedRootDirs]` (full replace, not merge).
- The CLI argument is only used as a fallback *when the client does not support roots*.

Claude Code supports roots and advertises **only its primary working directory** (`homework-5`), which replaced the configured parent path. `/add-dir` does **not** help — it adds a directory for Claude Code's own built-in tools but does not push a `roots/list_changed` update to MCP servers.

#### Fix: run the server in Docker with a remapped bind-mount

Running the server in a container and bind-mounting the host directory to a *different* container path makes the server structurally immune to the roots override:

```json
"filesystem": {
  "command": "docker",
  "args": [
    "run", "-i", "--rm",
    "--mount", "type=bind,src=/home/alex/Sites/study/homework,dst=/projects",
    "mcp/filesystem",
    "/projects"
  ]
}
```

**Why it works:** the server validates each client-supplied root with `fs.realpath`/`fs.stat` *inside its own filesystem*. Claude Code sends host paths (`/home/alex/.../homework-5`), which **do not exist inside the container** → all rejected → `validatedRootDirs.length === 0` → the server keeps its `/projects` argument.

### Task 3 — Notion MCP

Configured as a remote HTTP server (`https://mcp.notion.com/mcp`); authentication is handled by Notion's hosted OAuth flow on first connect.

### Task 4 — Custom `lorem` MCP server (PHP)

Built with the official PHP SDK (`mcp/sdk`) instead of Python/FastMCP. It exposes the contents of `lorem-ipsum.md` as both an MCP **Resource** (`file://lorem-ipsum.md`) and a **Tool** (`read`), each returning the first `wordCount` words (default `30`). Runs as a self-contained Docker image over STDIO and is registered in `.mcp.json` as `lorem`.

- Source & design: [lorem-mcp-server/](./lorem-mcp-server/), [lorem-mcp-server/SPEC.md](./lorem-mcp-server/SPEC.md)
- Build, run, connect, test: [HOWTORUN.md](./HOWTORUN.md)
