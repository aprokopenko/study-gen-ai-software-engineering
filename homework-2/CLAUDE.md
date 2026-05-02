# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Homework 2: Intelligent Customer Support System — PHP REST API for support ticket management with multi-format import, auto-classification, and a comprehensive test suite.

## Tech Stack

- **PHP 8.5** — `declare(strict_types=1)` in every file
- **Slim 4** (PSR-7/15), **PHP-DI 7** (autowiring), **Medoo 2.2** (query builder), **SQLite**, **Ramsey UUID 4**, **PHPUnit 11**

## Commands

All PHP execution goes through Docker — never run `php` or `composer` on the host.

```bash
make setup      # build + up + composer install + migrate + fix permissions
make up / down / restart / logs / shell
make phpunit    # run test suite
make test       # curl smoke test against localhost:3000
make composer args="require foo/bar"
make chown      # fix host-side ownership of src/ and data/
```

## Key Conventions

**`config(string $key, mixed $default = null)`** — dot-notation loader: `config('database.database_file')`. First segment is the filename under `config/`.

**`container(string $abstract)`** — resolves and returns a wired service: `container(MyService::class)`.

**`Database::query()`** — returns the raw Medoo instance. **`Database::migrate(string $schemaPath)`** — runs schema SQL on startup.

**Controllers** extend `AbstractController`, action methods signature: `(Request $request, Response $response, array $args): Response`. Use `$this->json($response, $data, $status)`.

**Routes** — all defined in `app/routes.php` as a closure receiving the Slim `App` instance.

**Error shapes:**
- `400`: `{"error": "Validation failed", "details": {"field_name": "error message", ...}}`
- `404`: `{"error": "Not found"}`

## Code Style

Follow PSR-12. Do not align whitespace in arrays, object definitions, or assignments — keys and values go on the next character after the separator, no padding to align `=>` or `=` across lines.

## Testing

Tests are split into two suites:

| Folder | Boundary | Uses |
|---|---|---|
| `tests/Unit/` | Single class, collaborators stubbed | No `App`, no DB |
| `tests/Feature/` | Full HTTP request → response | `AppTestCase` |

`AppTestCase` sets `DATABASE_PATH=:memory:` and calls `ContainerFactory::reset()` before each test. Helpers: `get()`, `postJson()`, `postRaw()`.

Mirror production paths inside each folder (e.g. `app/Parsers/CsvTicketParser.php` → `tests/Unit/Parsers/CsvTicketParserTest.php`).

Fixtures live in `tests/fixtures/valid/` and `tests/fixtures/invalid/`.

Target coverage: **>85%**.