#!/usr/bin/env bash
# .claude/hooks/coverage-gate-pretool.sh
#
# PreToolUse hook — intercepts `git push` commands initiated through Claude Code
# and blocks them when test coverage < 80%.
#
# Called by Claude Code with the tool input JSON on stdin.
# Exit code 2 = block the action (Claude Code PreToolUse contract).
# Exit code 0 = allow the action to proceed normally.

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // empty' 2>/dev/null)

# Only intercept commands that start with "git push"
if [[ "$COMMAND" != git\ push* ]]; then
    exit 0  # not a git push — let it through
fi

# Resolve project root from $CLAUDE_PROJECT_DIR (set by Claude Code) or fallback
# to the directory two levels up from this hook file.
if [ -n "${CLAUDE_PROJECT_DIR:-}" ]; then
    PROJECT_DIR="$CLAUDE_PROJECT_DIR"
else
    HOOK_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    PROJECT_DIR="$(cd "$HOOK_DIR/../.." && pwd)"
fi

GATE="$PROJECT_DIR/bin/coverage-gate.sh"

if [ ! -x "$GATE" ]; then
    echo "Coverage gate script not found or not executable: $GATE" >&2
    echo "Push BLOCKED (coverage gate misconfigured)." >&2
    exit 2
fi

# Run the gate; on failure, exit 2 to block the push.
if ! "$GATE"; then
    echo "" >&2
    echo "Git push blocked by coverage gate." >&2
    exit 2
fi

exit 0  # coverage passed — allow the push
