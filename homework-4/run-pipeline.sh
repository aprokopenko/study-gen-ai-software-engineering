#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"   # workspace root must be the project dir so .claude/settings.local.json loads and relative permission rules anchor correctly
BUGS_DIR="$ROOT/context/bugs"

# DEBUG: remove this line to process all bugs
#MAX_BUGS=1

processed=0
skipped=0

for bug_dir in "$BUGS_DIR"/*/; do
  bug_id="$(basename "$bug_dir")"
  if [[ -f "$bug_dir/test-report.md" ]]; then
    echo "[skip] bugs/$bug_id — already processed (test-report.md present)"
    skipped=$((skipped+1))
    continue
  fi
  echo "[run] bugs/$bug_id — invoking orchestrator"
  claude -p --model claude-opus-4-7 --effort xhigh "/pipeline-orchestrator context/bugs/$bug_id"
  processed=$((processed+1))
  echo '-----'
  if [[ -n "${MAX_BUGS:-}" && "$processed" -ge "$MAX_BUGS" ]]; then
    echo "[debug] Stopped after $processed bug(s). Remove MAX_BUGS to run all."
    break
  fi
done

echo "Done. processed=$processed skipped=$skipped"
