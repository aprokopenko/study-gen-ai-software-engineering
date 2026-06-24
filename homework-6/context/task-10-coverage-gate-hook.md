# Task 10 — Coverage-gate hook (blocks push < 80%)
**Date:** 2026-06-24

## Research / Decisions

### Claude Code hook schema (context7 query)
- Library: `/websites/code_claude` (High reputation, 9723 snippets, benchmark 86.2)
- Query: "PreToolUse hook block git push exit code non-zero blocking push hooks stdin jq permissionDecision"
- Finding: PreToolUse hooks fire before any Bash tool call. The hook receives tool input
  JSON on stdin. **Exit code 2 = block the action** (not exit 1). The `if` field
  (`"Bash(git push*)"`) limits the hook to commands matching `git push*` without
  running it for every Bash invocation. `${CLAUDE_PROJECT_DIR}` is resolved by
  Claude Code at runtime to the project root.
- Schema used:
  ```json
  "hooks": {
    "PreToolUse": [{
      "matcher": "Bash",
      "hooks": [{
        "type": "command",
        "if": "Bash(git push*)",
        "command": "${CLAUDE_PROJECT_DIR}/.claude/hooks/coverage-gate-pretool.sh"
      }]
    }]
  }
  ```

### Gate logic reuse
`make coverage` (Task 1) already: runs PHPUnit with pcov in Docker, parses
`Lines: NN.NN%`, prints `Measured coverage: NN%`, and **exits non-zero when below
COVERAGE_THRESHOLD (80)**. The gate script reuses this target entirely and parses
its output for the display summary. No new library or parsing logic needed.

## What was created / changed

| File | Action |
|------|--------|
| `scripts/coverage-gate.sh` | Created (executable `rwxrwxr-x`). Runs `make coverage`, parses `Measured coverage: NN.NN%`, prints measured % and 80% threshold, exits 1 on failure (missing pct, below threshold, or make non-zero). |
| `.githooks/pre-push` | Created (executable `rwxrwxr-x`). Git pre-push hook; delegates to `scripts/coverage-gate.sh`; blocks push on non-zero exit. |
| `.claude/hooks/coverage-gate-pretool.sh` | Created (executable `rwxrwxr-x`). Reads stdin, extracts `.tool_input.command` via jq, checks for `git push*`, runs `scripts/coverage-gate.sh`, exits 2 to block push in Claude Code. |
| `.claude/settings.json` | Updated. Added `hooks.PreToolUse` entry wiring `coverage-gate-pretool.sh` to intercept `Bash(git push*)` commands. Existing `enabledMcpjsonServers` and `permissions` keys preserved. |
| `Makefile` | Updated. Added `install-hooks` target (`git config core.hooksPath .githooks`) and added `install-hooks` to `.PHONY` list. |

## Wiring paths

### Path 1 — git pre-push hook
1. Developer runs: `make install-hooks` (once per clone) → sets `core.hooksPath .githooks`
2. On every `git push`: git calls `.githooks/pre-push` → which calls `scripts/coverage-gate.sh`
3. If coverage < 80%: gate exits 1 → git pre-push contract receives non-zero → push is blocked
4. All PHP/PHPUnit/coverage runs inside Docker via `make coverage`; no host-side PHP needed

### Path 2 — Claude Code PreToolUse hook
1. When Claude Code runs a `git push*` Bash command, `coverage-gate-pretool.sh` fires first
2. Script parses `.tool_input.command` from stdin, confirms it's a `git push`
3. Runs `scripts/coverage-gate.sh`; if gate fails, exits 2 → Claude Code blocks the tool call
4. Claude sees the stderr output as feedback explaining why the push was blocked

## Self-verification

### PASS path (real suite — 93.49%)
Command: `bash scripts/coverage-gate.sh` (from project root)
Result:
```
  Measured coverage : 93.49%
  Required threshold: 80%

  OK: coverage 93.49% meets the 80% threshold.
  Push allowed.
EXIT CODE: 0
```

### FAIL path (simulated — no real coverage change made)
Verified via inline shell logic replicating the gate's numeric check with FAKE_PCT=75.00:
```
  Measured coverage : 75.00%
  Required threshold: 80%

  FAIL: coverage 75.00% is below the required 80%.
  Push BLOCKED. Improve test coverage and try again.
Exit code: 1
```

### Exactly 80% PASSES (gate is `< 80%`, not `<= 80%`)
```
PASS value for 80.00% >= 80: 1
ALLOWED (exactly 80% passes — gate is < 80%)
```

### Missing/unparseable coverage → FAILURE
```
ERROR: Could not parse measured coverage from make output.
Treating missing/unreadable coverage report as FAILURE.
Push BLOCKED.
Exit code: 1
```

### settings.json is valid JSON
Verified via `python3 -m json.tool .claude/settings.json` — parsed successfully.

### All scripts are executable
```
-rwxrwxr-x  .claude/hooks/coverage-gate-pretool.sh
-rwxrwxr-x  .githooks/pre-push
-rwxrwxr-x  scripts/coverage-gate.sh
```
