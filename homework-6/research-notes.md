# Research Notes — homework-6 banking pipeline

Cumulative ledger of library and tech-stack decisions made per task.
Later tasks MUST read this file first and reuse these choices.
Append new decisions below; never re-research an already-recorded item.

---

## Task 1 — Docker environment & developer tooling (2026-06-23)

### Query 1: PHP CLI Docker image — latest stable tag

- **context7 search term:** `PHP` → library ID `/serversideup/docker-php` (High reputation, 132 snippets)
- **Query:** "latest stable PHP CLI image tag version 8.4 8.3"
- **Finding:** The serversideup docs show `serversideup/php:8.5-cli` as their current tag in code
  examples. However, the official Docker Hub image `php:8.4-cli` is preferred for this project
  because:
  1. PHP 8.4 is the confirmed current stable release (GA: November 2024).
  2. The official `php:8.4-cli` image uses a generic non-root-capable base (Debian slim) that
     works naturally with `user: "${UID}:${GID}"` in docker compose without fighting a pre-set
     unprivileged user (serversideup images default to `www-data`).
  3. `pecl install pcov` is a one-liner on the official image; serversideup's `install-php-extensions`
     tool adds a third-party layer.
- **Decision:** `FROM php:8.4-cli` (official Docker Hub image, PHP 8.4 stable).

### Query 2: Coverage driver — pcov vs xdebug

- **context7 search term:** `PHPUnit` → library ID `/websites/phpunit_de_en_12_5` (High reputation, 1455 snippets)
- **Query:** "PHPUnit 12 PHP version requirements latest stable release pcov coverage driver"
- **Finding:**
  - PHPUnit 12 requires PHP 8.3 or later (confirmed from docs).
  - PHPUnit 12.5 is the latest stable release.
  - Both pcov and xdebug are supported; pcov is listed first in the docs, is lighter (coverage
    only, no debugging overhead), and is the preferred driver for CI/CD pipelines.
- **Decision:** `pecl install pcov` + `docker-php-ext-enable pcov`. pcov is enabled at runtime
  with `-d pcov.enabled=1` (already wired in the Makefile `coverage` target).

### Query 3: PHP extensions for decimal/money handling

- **context7 search term:** `/serversideup/docker-php`
- **Query:** "pcov coverage extension install PHP 8.4 8.5 Dockerfile"
- **Finding:** `bcmath` is the standard PHP extension for arbitrary-precision decimal arithmetic
  (no float rounding). `gmp` provides big integer arithmetic as a complementary fallback.
  Both are available as built-in extensions installable via `docker-php-ext-install`.
- **Decision:** Install `bcmath` and `gmp` via `docker-php-ext-install`.

### Pinned versions (Task 1)

| Component        | Pinned version / tag  | How confirmed          |
|------------------|-----------------------|------------------------|
| PHP runtime      | `8.4` (php:8.4-cli)   | context7 + PHP release schedule |
| PHPUnit          | `^12.5`               | context7 PHPUnit 12.5 docs |
| Composer         | `2` (composer:2 image)| Official Composer Docker image |
| Coverage driver  | pcov (pecl)           | PHPUnit 12.5 docs      |
| bcmath           | bundled with PHP 8.4  | php-src docs           |
| gmp              | bundled with PHP 8.4  | php-src docs           |

---

## Task 2 — Shared infrastructure: envelope, file queue, money, audit logger (2026-06-23)

### Query 1: Decimal/money library for PHP — brick/math vs native bcmath

- **context7 search term:** `brick/math` → library ID `/brick/math` (High reputation, 89 snippets, benchmark 88.46)
- **Also found:** `/brick/money` (High reputation, 141 snippets, benchmark 80.6) — a higher-level currency type
- **Query:** "BigDecimal arbitrary precision decimal multiply divide subtract round half-up string parsing version PHP 8.4"
- **Finding:**
  - `brick/math` provides `BigDecimal` with methods `multipliedBy()`, `minus()`, `toScale()`, and `RoundingMode` enum.
  - `toScale($scale, RoundingMode::HALF_UP)` implements half-up rounding (ties round toward +∞) to arbitrary decimal places.
  - Note: in brick/math 0.12, `RoundingMode` is a PHP enum — constants are accessed as `RoundingMode::HALF_UP` (SCREAMING_SNAKE_CASE), NOT `RoundingMode::HalfUp` (PascalCase shown in some context7 snippets from older versions).
  - Requires PHP 8.2 or later — compatible with our PHP 8.4 base.
  - `bcmath` extension (already in the image from Task 1) is used as the backend by `brick/math` when available, providing extra performance.

### Query 2: Installation and version confirmation

- **context7 search term:** `/brick/math`
- **Query:** "composer require brick/math installation version requirement PHP 8 latest stable release"
- **Finding:** `composer require brick/math` installs the package. Latest stable release confirmed as **0.12.3** (installed via container: `brick/math (0.12.3)`).
- **`composer require` output (inside container):** `Locking brick/math (0.12.3)` / `Installing brick/math (0.12.3): Extracting archive`

### Decision: brick/math 0.12.3

**Chosen library:** `brick/math ^0.12` (pinned in `composer.json`)

**Rationale:**
1. Pure-PHP arbitrary-precision BigDecimal avoids float entirely — amounts stay as strings.
2. `RoundingMode::HALF_UP` satisfies the spec's "half-up to the currency minor unit" requirement.
3. Uses bcmath as its backend (already installed from Task 1) for better performance.
4. `brick/money` was considered but is overkill — it adds currency objects and formatting on top; we only need decimal arithmetic and the Validator handles ISO 4217 validation separately.
5. Native bcmath functions (`bcmul`, `bcsub`, `bcround`) were also considered, but `brick/math` provides a cleaner OOP API and handles edge cases (e.g. RoundingNecessaryException) more explicitly.

### Pinned versions (Task 2 additions)

| Component       | Pinned version | How confirmed                            |
|-----------------|----------------|------------------------------------------|
| brick/math      | `0.12.3`       | `composer require` inside container output |

---
## Task 3 — Validator stage: ISO 4217 currency validation (2026-06-23)

### Query 1: PHP ISO 4217 library search

- **context7 search term:** `iso4217` → library ID `/dahlia/iso4217`
- **Finding:** Python-only package — not applicable to PHP.

### Query 2: brick/money — ISO 4217 validation via Currency::of()

- **context7 search term:** `brick/money` → library ID `/brick/money` (High reputation, 141 snippets, benchmark 80.6)
- **Query:** "ISO 4217 currency code list validation check if currency code is valid PHP"
- **Finding:**
  - `Currency::of('USD')` performs an ISO 4217 lookup; unknown codes throw `UnknownCurrencyException`.
  - `IsoCurrencyProvider::getInstance()->getAvailableCurrencies()` returns ~170+ active codes (keyed by alpha code).
  - `Currency::of('XYZ')` throws — clean exception-based rejection pattern.
  - Already assessed in Task 2 research as overkill for arithmetic-only work; same applies here for validation-only.

### Decision: hardcoded constant set in `src/Config/Iso4217.php`

**Chosen approach:** `BankingPipeline\Config\Iso4217::isValid(string $code): bool`
backed by a `const CODES = ['USD' => true, 'EUR' => true, ...]` array (~170 active codes).

**Rationale:**
1. Spec explicitly anticipates "an ISO 4217 set living in Config".
2. `brick/money` (and its `Currency::of()`) is overkill for validation-only; no new Composer dep needed.
3. PHP constant array with `isset()` is zero-runtime-cost and codebase-inspectable.
4. ISO 4217 active-currency list is stable; infrequent updates are a trivial one-file edit.

**No new Composer dependency** — `make install` not required for Task 3.

---
## Task 6 — Orchestrator / Integrator (2026-06-23)

No new library research required. All dependencies (brick/math, PHPUnit 12, shared
helpers) were already resolved in Tasks 1–5. No context7 query was performed.

### Architecture decision: injectable AuditLogger in Integrator

To prevent audit log lines (written to STDERR by default) from bleeding through
during `make test`, the Integrator accepts an optional `AuditLogger` constructor
parameter. Tests inject `new AuditLogger(sink: fn() => null)`. The CLI entrypoint
passes `null` (defaults to STDERR). No new library involved.

## Task 7 — Run summary reporter (2026-06-23)

No new library research required. All dependencies (brick/math, PHPUnit 12, Envelope,
FileQueue) were already resolved in Tasks 1–5. No context7 query was performed.

### Decision: reason-grouping scheme

Raw rejection reason strings are normalised into four canonical categories using
case-insensitive substring matching (no new parser or library needed):

| Category            | Trigger pattern                                           |
|---------------------|-----------------------------------------------------------|
| `missing-field`     | contains "missing" OR "required field"                    |
| `non-positive-amount` | contains "amount" AND ("zero"/"positive"/"greater than") |
| `invalid-currency`  | contains "currency" OR "iso 4217"                         |
| `high-risk`         | contains "high-risk"/"high risk" OR ("risk" AND "score")  |
| `unknown`           | fallback for unrecognised strings                         |

### Decision: self-summary-file exclusion

Reporter skips filenames listed in a `SUMMARY_FILES` constant (`summary.json`,
`summary.txt`) during the glob scan, preventing double-counting on re-summarise.

### Decision: malformed file handling

Files that cannot be read or parsed by `Envelope::fromJson()` are skipped and their
filenames are appended to the `errors` key in the returned summary array. This avoids
silent miscounting without crashing the reporter.

<!-- Append new task decisions below this line -->

---

## Task 8 — Custom MCP server (pipeline-status) (2026-06-23)

### Query 1: Resolve PHP MCP SDK library ID

- **context7 search term:** `mcp/sdk` → library ID `/modelcontextprotocol/php-sdk`
  (High reputation, 959 snippets, benchmark 61.4 — the official PHP SDK)
- **Finding:** Official PHP SDK for Model Context Protocol. Provides `Server::builder()`,
  `StdioTransport`, `#[McpTool]`, `#[McpResource]` PHP attributes, and an explicit
  `addTool()` / `addResource()` builder API that does not require Symfony Finder.

### Query 2: StdioTransport + tools/resources registration

- **context7 search term:** `/modelcontextprotocol/php-sdk`
- **Query:** "StdioTransport server tools resources register latest version installation composer require"
- **Finding:**
  - `composer require mcp/sdk` installed **v0.6.0** (2026-06-02) — latest stable.
  - `Server::builder()->addTool(handler: $closure, name: '...', description: '...', inputSchema: [...])`
    registers tools without auto-discovery (no Symfony Finder dependency needed).
  - `Server::builder()->addResource(handler: $closure, uri: '...', mimeType: '...')`
    registers static resources.
  - `StdioTransport` reads JSON-RPC frames from STDIN and writes to STDOUT.
    **No stray stdout allowed** — any echo/print before/after `$server->run()` breaks the stream.
  - The container must be launched with `-i` (interactive stdin) and NO `-t` (no TTY).
  - The project source must be mounted so `vendor/autoload.php` and `mcp/server.php` are reachable.

### Decision: mcp/sdk v0.6.0

**Chosen package:** `mcp/sdk ^0.6.0` (pinned in `composer.json`)

**Rationale:**
1. Only official PHP MCP SDK — avoids `php-mcp/server` (ReactPHP-based, known to thrash).
2. `addTool()` / `addResource()` builder API requires no Symfony Finder; simpler and leaner.
3. StdioTransport is the correct transport for one-shot stdio containers.
4. v0.6.0 is the current latest stable release (2026-06-02).

### `.mcp.json` launch line and rationale

```json
"command": "docker",
"args": ["run", "-i", "--rm", "-w", "/app", "-v", "${PWD}:/app",
         "-v", "${PWD}/shared:/app/shared:ro", "homework-6-app", "php", "mcp/server.php"]
```

**Rationale:**
- `-i` (not `-t`): interactive stdin for JSON-RPC frames; TTY would corrupt the byte stream.
- `-w /app`: working directory matches the compose service so relative paths (`mcp/server.php`,
  `vendor/autoload.php`) resolve correctly.
- `-v ${PWD}:/app`: mounts the full project source (code + vendor/) read-write so the server
  can load autoload.php.  The spec says read-only is fine for shared/; the source mount must
  be read-write for Composer's autoloader cache.
- `-v ${PWD}/shared:/app/shared:ro`: second, explicit read-only mount so results are readable
  but the MCP server cannot accidentally modify pipeline output.
- `homework-6-app`: the project's pre-built Docker image (Task 1).

### Pinned versions (Task 8 additions)

| Component | Pinned version | How confirmed                                  |
|-----------|----------------|------------------------------------------------|
| mcp/sdk   | `v0.6.0`       | `composer require` inside container output     |
