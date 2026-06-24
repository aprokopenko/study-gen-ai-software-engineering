#!/usr/bin/env bash
# bin/coverage-gate.sh
#
# Coverage gate: runs `make coverage` inside Docker (pcov), parses the measured
# percentage, and exits non-zero when coverage < COVERAGE_THRESHOLD (80%).
#
# Designed to be called from:
#   - .githooks/pre-push  (git pre-push hook)
#   - .claude/settings.json PreToolUse hook (Claude Code git-push gate)
#
# Usage:
#   bin/coverage-gate.sh
#
# Exit codes:
#   0 — coverage >= 80% (push allowed)
#   1 — coverage < 80%, missing/unreadable report, or unparseable percentage
#
# The script prints measured coverage and the threshold so a failure is
# self-explanatory in both the terminal and the Claude Code hook output.

set -euo pipefail

THRESHOLD=80
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "------------------------------------------------------------"
echo "  Coverage gate — threshold: ${THRESHOLD}%"
echo "  Running: make coverage (Docker/pcov)"
echo "------------------------------------------------------------"

# Run coverage in Docker via the Makefile target.
# `make coverage` already:
#   - runs PHPUnit with pcov in the container
#   - prints "Measured coverage: NN.NN%"
#   - exits non-zero when below COVERAGE_THRESHOLD (80)
#   - writes coverage.xml (clover)
#
# We capture its full output so we can re-print the measured percentage
# in a clear summary line even when make itself succeeds.

COVERAGE_OUTPUT_FILE="$(mktemp /tmp/coverage-gate-output.XXXXXX)"
trap 'rm -f "$COVERAGE_OUTPUT_FILE"' EXIT

# Run make coverage; capture combined stdout+stderr; preserve exit code.
set +e
cd "$PROJECT_DIR"
make coverage 2>&1 | tee "$COVERAGE_OUTPUT_FILE"
MAKE_EXIT=$?
set -e

# Parse the percentage that `make coverage` prints:
#   "Measured coverage: 93.49%"
PCT=$(grep -oE 'Measured coverage: [0-9]+(\.[0-9]+)?' "$COVERAGE_OUTPUT_FILE" \
      | grep -oE '[0-9]+(\.[0-9]+)?$' \
      | head -1)

echo "------------------------------------------------------------"

if [ -z "$PCT" ]; then
    echo "  ERROR: Could not parse measured coverage from make output."
    echo "         Treating missing/unreadable coverage report as FAILURE."
    echo "  Push BLOCKED."
    echo "------------------------------------------------------------"
    exit 1
fi

echo "  Measured coverage : ${PCT}%"
echo "  Required threshold: ${THRESHOLD}%"

# make coverage already enforces the threshold and exits non-zero on failure.
# We also do our own numeric check so the gate works even if called directly
# against a pre-existing coverage.xml or with a different threshold override.
PASS=$(echo "$PCT $THRESHOLD" | awk '{print ($1 >= $2) ? "1" : "0"}')

if [ "$PASS" = "0" ] || [ "$MAKE_EXIT" -ne 0 ]; then
    echo ""
    echo "  FAIL: coverage ${PCT}% is below the required ${THRESHOLD}%."
    echo "  Push BLOCKED. Improve test coverage and try again."
    echo "------------------------------------------------------------"
    exit 1
fi

echo ""
echo "  OK: coverage ${PCT}% meets the ${THRESHOLD}% threshold."
echo "  Push allowed."
echo "------------------------------------------------------------"
exit 0
