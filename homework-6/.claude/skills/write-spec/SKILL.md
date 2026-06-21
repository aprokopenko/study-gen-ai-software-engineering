---
name: write-spec
description: >-
  Write a detailed technical specification before any implementation. Use when the
  user asks to "write a spec", "create a specification", plan a project or feature
  before coding, or turn requirements into a structured spec. Produces a
  markdown file with High-Level Objective, Mid-Level Objectives, Implementation
  Notes, Context, and per-component Low-Level Tasks.
---

# Write Specification

Generate a complete technical specification from the bundled template, grounded in
the actual project. Spec first, code second — the spec is the contract everything
else is built against.

## Inputs

- **Target** — what the spec is for (a project, feature, or component). If unclear,
  ask one focused question before proceeding.
- **Output path** — default `specification.md` at the project root unless the user
  names another.
- **Template** — `templates/specification-template.md` (next to this file). Always
  base the output on it so the structure stays consistent.

## Procedure

1. **Gather context.** Read whatever describes the project: `AGENTS.md`,
   `README.md`, existing specs, sample data, and any files the user points to. Note
   the fixed constraints (stack, standards) and what is deliberately left open. Ask user any additional questions you think valuable or may improve the quality of the spec to fulfil the specs context.   
2. **Investigate the stack and propose a project structure.** Before writing the
   spec, work out *where the code will live*. Identify the language/framework (from
   `AGENTS.md` or the user; if open, the structure follows the most likely choice and
   says so). Research the idiomatic layout for that stack — its build/dependency
   conventions, source vs. test roots, and the standard way to separate concerns
   (e.g. domain logic vs. I/O vs. entrypoints vs. config) — using project files,
   `context7`, or the web as needed. Draft a **rough directory tree** that reflects
   real separation of concerns, not a flat pile of scripts: group related modules,
   name folders by responsibility, and place tests/config/entrypoints where the
   ecosystem expects them. **Always present this tree to the user and get their
   confirmation before continuing** — do not load the template or write any section
   until the structure is approved. Incorporate any changes they request.
3. **Load the template** from `templates/specification-template.md`.
4. **Fill every section** in order:
   - **High-Level Objective** — 1-2 sentence, the outcome.
   - **Mid-Level Objectives** — up to 15-20 concrete (depends on feature size), testable bullets in plain
     stakeholder language. Describe *what*, not *how*. Keep technology, numbers, and
     mechanics out of this section.
   - **Implementation Notes** — the constraints and the mechanics/numbers that make
     the objectives concrete (thresholds, formats, standards, audit/logging,
     security, PII). State fixed stack constraints; mark open choices as open rather
     than inventing specific libraries. Include the **proposed project structure**
     from step 2 here (a directory tree with a one-line note on what each folder
     holds); the Low-Level Task file paths must match it.
   - **Context** — beginning state (existing files/inputs) and ending state
     (artifacts produced, quality gates).
   - **Low-Level Tasks** — one `###` component per piece to build, in execution
     order, using the light `File / Function / Prompt / Details` field labels from
     the template. Fill the fields in your own words; never copy the template's
     placeholder text or restate it as questions, and don't wrap components in fenced
     code blocks. The `Prompt` is the exact instruction a coding agent would run for
     that piece.
5. **Write the spec** to the output path.
6. **Self-check** before finishing:
   - All five sections are present and non-empty.
   - Mid-Level Objectives are testable and free of implementation detail.
   - A proposed project structure is present and reflects real separation of concerns
     for the stack — not a flat list of scripts.
   - Every component named in the project has its own Low-Level Task heading, and its
     file path sits within the proposed project structure.
   - Beginning/ending context matches the objectives.

## Guidelines

- Keep Mid-Level Objectives and Implementation Notes cleanly separated — the "what"
  versus the "how/numbers". This is the most common spec mistake.
- Don't pin library or framework choices the project intends to leave open; describe
  the principle and note that the implementation step resolves it.
- Prefer precise, verifiable statements over aspirational ones.
- Match the project's own vocabulary and conventions (read `AGENTS.md` for these).
- Propose a project structure that an engineer in that ecosystem would recognise:
  separate domain logic from I/O and entrypoints, keep tests/config where the stack
  expects them, and group by responsibility so the layout is easy to navigate. Avoid a
  flat dump of scripts. Keep every Low-Level Task file path consistent with it.
