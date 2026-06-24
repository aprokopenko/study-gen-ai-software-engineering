# Task 13 — Documentation (README & run guide)

**Date:** 2026-06-24

## What was produced

### `README.md`
- Author line: "Created by Alex Prokopenko" (prominent, immediately below the title).
- 2-paragraph description of the batch transaction-processing pipeline.
- One bullet per stage: Validator, Fraud Detector, Settlement, Reporter — with
  domain rules inline (ISO 4217, ≥ $10 000 threshold, 0.25% fee, score cutoff 60).
- ASCII architecture diagram showing both the happy path (validate → fraud-score →
  settle → results) **and** the rejected-path branch (invalid + high-risk → results/
  with reason), as required by the spec edge-case note.
- Shared directory layout and envelope schema snippet.
- Tech-stack table: PHP 8.4, Docker + Compose, Composer 2, PHPUnit ^12.5, pcov,
  brick/math ^0.12, mcp/sdk ^0.6.0, Iso4217 constant set — all pulled from
  `composer.json` and `research-notes.md`.
- Sample run result table (8 transactions, 5 settled / 3 rejected) matching the
  context provided in the assignment.

### `HOWTORUN.md`
- Numbered setup-to-demo steps: build → install → (install-hooks) → run → validate
  → test → coverage → mcp.
- `make clean-shared` noted between runs.
- MCP server section: `.mcp.json` launch command, the 3 exposed items
  (`get_transaction_status`, `list_pipeline_results`, `pipeline://summary`), and an
  example interaction.
- Claude Code skills section: `/run-pipeline` and `/validate-transactions` slash
  commands with descriptions.
- Quick-reference table of all 12 make targets.

## Consistency checks

1. **Make targets** — verified against `make help` output (all 12 targets matched:
   help, build, install, install-hooks, run, validate, test, coverage, mcp, shell,
   clean-shared, clean, reset).
2. **Versions** — PHP 8.4, PHPUnit ^12.5, brick/math ^0.12, mcp/sdk ^0.6.0 pulled
   directly from `composer.json`; pcov and Composer 2 from `research-notes.md`.
3. **Entrypoints** — `bin/run-pipeline`, `bin/validate-transactions`,
   `bin/coverage-gate.sh`, `mcp/server.php` all confirmed present.
4. **MCP tools/resources** — cross-checked against `mcp/server.php` (addTool x2 +
   addResource x1).
5. **Domain numbers** — fee 0.25%, threshold $10 000, score cutoff 60, gate 80% —
   consistent with spec and source code.
6. **Diagram** — rejected branch present for both invalid (Validator) and high-risk
   (Fraud Detector) paths.
7. **No host commands** — all instructions use `make` targets only.

## Self-verification

- `make help` run in Docker to confirm target names and descriptions match docs.
- Both files confirmed written successfully (no Write errors).
- Markdown structure reviewed: headers, code blocks, and tables all properly fenced.
