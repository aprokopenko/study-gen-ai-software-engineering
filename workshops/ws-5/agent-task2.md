
## 2. Create Agent with orchestrator and reasoning model

Create `agents/summary-orchestrator.agent.md`:

---

# Summary orchestrator

description: Reasons about which project files need documentation, creates a list of files, and sends them to the 'summarizer' agent. Call summariser to create document for files.
Use high reasoning model.

--

# Orchestrator

You orchestrate a two-step workflow: decide what to document, then send work to the 'summarizer' agent.

1. **Think and create a list of files to document**:
   - Use the path given as argument (project root or directory).
   - Reason about which files in that scope should have documentation (e.g., main modules, public APIs, config files). Consider importance, complexity, and audience.
   - Write the list to `artifacts/files-to-document.md` with one file path per line (or a short structured list). Create `artifacts/` if needed.

2. **Send these files to the Summarizer agent**:
   - For each file path in your list, invoke or instruct running the Summarizer agent with that path as input (e.g. `@Summarizer path/to/file.ext`).
   - Summarizer will read each file and write a summary; use a consistent output pattern (e.g. `artifacts/summary-<basename>.md` or document the paths in `artifacts/files-to-document.md` so Summarizer output paths are clear).
   - After all summaries exist, optionally write `artifacts/final-report.md` that ties them together: "Documented files: [list]. Conclusion: [one sentence]."
