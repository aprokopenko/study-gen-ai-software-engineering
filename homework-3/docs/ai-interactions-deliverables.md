# AI Interaction Log — Homework 3 Deliverables

**Model**: Claude Sonnet 4.6 (`claude-sonnet-4-6`)
**Tool / IDE**: Claude Code via the VS Code extension

## Goal of the session

Generate the remaining required deliverables for Homework 3 based on the already-drafted specification files (`specs/spending-caps-app.md`, `specs/spending-caps-tasks.md`): an `AGENTS.md`, a Claude Code rules file (`.claude/CLAUDE.md`), and a `README.md`.

## Interaction flow

### 1. Clarifying what "Claude rules" means

The session started with a question about what the third deliverable — "editor / AI rules" — actually is. The distinction drawn: `AGENTS.md` is portable documentation any agent or human can read; `.claude/CLAUDE.md` is a machine-loaded file Claude Code reads at session start and acts on directly, functioning as standing orders rather than reference material.

### 2. Generating AGENTS.md

Generated from the spec in one pass. Seven sections: project context, tech stack table, domain rules (the nine must-not-violate rules from §5.6 expanded with rationale), code conventions (IDs, money, time, enums, error responses, logging, testing), security and compliance constraints table, edge-case handling expectations, and verification expectations.

### 3. Generating .claude/CLAUDE.md

First draft had eight sections mirroring AGENTS.md closely: hard limits, naming conventions, patterns, testing defaults, CI requirement, sensitive defaults. After generation, the user independently refactored the file down to a leaner format — keeping only patterns, testing defaults, task verification, and sensitive defaults, with a header directive to read `AGENTS.md` first. The two files were then compared for overlap.

**Overlap identified (not fixed in this session):**
- All six "Patterns to follow" bullets exist in AGENTS.md (§3, §4, §6).
- Testing defaults are near-verbatim copies of AGENTS.md §4 Testing subsection.
- `make ci` requirement duplicates the last line of AGENTS.md §7.
- Only "Sensitive defaults" is unique to CLAUDE.md.

### 4. Generating README.md

Three iterations before the final form:

1. **First draft** — project-first layout (description, tech stack, quick start, docs, structure, then homework metadata at the bottom). User feedback: too project-heavy, homework sections buried.
2. **Second draft** — attempted to match TASKS.md structure (Student & task summary / Rationale / Industry best practices as top-level sections) but user had already edited the file in the interim, causing a write conflict.
3. **Third draft** — clean rewrite matching TASKS.md exactly: three top-level sections, no project boilerplate. User then trimmed the file slightly (removed `docs/ai-interactions-specs.md` from the files table, minor wording edits) and renamed "Student & task summary" to "Task summary".

### 5. Key decisions

- **AGENTS.md vs CLAUDE.md split**: AGENTS.md is the authoritative, verbose reference; CLAUDE.md is a short prompt-style reminder that defers to AGENTS.md for detail. This mirrors the pattern of a project CLAUDE.md pointing to a more complete spec.
- **Industry best practices as a table**: chosen over prose to make spec references scannable and directly linkable per the homework requirement ("where they appear in the spec").
- **Performance targets justified per entry**: each target in the Rationale table includes the FinTech-specific reasoning (partner retry behaviour, UX expectations, batch headroom), satisfying the homework requirement to explain *why* the numbers are reasonable.
