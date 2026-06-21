# [Project / Feature Name] Specification

> Ingest the information in this file, implement the Low-Level Tasks, and generate
> the work that satisfies the High-Level and Mid-Level Objectives.

## 1. High-Level Objective

- [1-2 clear sentence describing what is being built and the outcome it delivers.]

## 2. Mid-Level Objectives

- [Concrete, testable objective stated in plain stakeholder language.]
- [Each describes *what* the system must do, not *how*.]
- [No technology, numbers, or mechanics here — those belong in Implementation Notes.]
- [Mention important edge cases/.]
- [Based on feature size aim not greater than 15-20 objectives.]

## 3. Implementation Notes

- [Technical constraints, standards, and the mechanics/numbers behind the objectives.]
- [Domain rules and the values that make them concrete (thresholds, formats, limits).]
- [Cross-cutting concerns: logging/audit, error handling, security, PII handling.]
- [Any fixed stack constraints; leave open choices explicitly open.]

**Proposed project structure**

[A rough directory tree for the chosen stack, reflecting real separation of concerns —
group by responsibility (domain logic vs. I/O vs. entrypoints vs. config), and put
tests/build/config where the ecosystem expects them. The Low-Level Task file paths must
sit within this tree. If the stack is open, base the tree on the most likely choice and
say so.]

```
[project-root]/
├── [source-root]/      ← [what lives here]
│   ├── [area-a]/       ← [responsibility]
│   └── [area-b]/       ← [responsibility]
├── [entrypoint(s)]/    ← [how the system is launched]
├── [tests-root]/       ← [test layout mirroring the source]
└── [config/build files]
```

## 4. Context

### Beginning context
- [Files and resources that exist at the start.]
- [Current system state and available inputs.]

### Ending context
- [Files and artifacts that will exist at the end. May be specified as reference, not final implementation in case implementation defined as separate step]
- [Expected final state and deliverables, including quality gates.]

## 5. Low-Level Tasks

> One component per `###` heading, in execution order. Keep each self-contained. The
> fields below are a structure reference, not literal text to copy — fill each in your
> own words for the component, and do not restate these descriptions as questions.
> Omit a field that does not apply.

### 1. [Component name]

- **File(s):** [path(s) to create or update]
- **Function/Unit:** [signature or unit name]
- **Prompt:** [the exact instruction you would give an AI coding agent for this component]
- **Details:** [what it checks, transforms, or decides; inputs and outputs; edge cases it must handle]

### 2. [Component name]

- **File(s):** [path(s)]
- **Function/Unit:** [signature or unit name]
- **Prompt:** [exact instruction]
- **Details:** [specifics and edge cases]
