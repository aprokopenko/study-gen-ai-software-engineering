# Task 1 — Multi-Format Ticket Import API

## Goals & Extensibility Hooks

Build the 6 ticket endpoints + CSV/JSON/XML import. Reserve seams for later tasks so no
refactoring is needed.

| Future need | Hook reserved in T1 |
|---|---|
| T2: auto-classification on create | `ClassifierInterface` injected into `TicketService::create()`; `NullClassifier` default. Schema columns `classification_confidence`, `classification_reasoning`, `classification_keywords` already in place. |
| T2: classification audit log | `ticket_logs` table created in T1. |
| T5: bulk-import perf / concurrency | Stateless services, `Database::query()->insert($table, [rows])` batch insert, parsers return `iterable` so they can be made streaming later. |
| New formats (Excel, YAML…) | `ParserRegistry` keyed by content-type / extension. Add a class + register. |
| Deterministic tests | `ClockInterface` + `IdGeneratorInterface`. |

## Third-Party Libraries

| Library | Version | Purpose |
|---|---|---|
| `somnambulist/validation` | ^1.12 | Input validation (Laravel-style rules, dot-notation nested fields, `ErrorBag`) |
| `league/csv` | ^9.0 | CSV parsing (BOM handling, header mapping, streaming `Iterator`, `EscapeFormula` for CSV-injection) |
| `nesbot/carbon` | ^3.0 | Timestamp generation and ISO-8601 validation via `Carbon::parse()` |

Install: `make composer args="require somnambulist/validation:^1.12 league/csv:^9.0 nesbot/carbon:^3.0"`

### What the libraries replace

| Removed | Replaced by |
|---|---|
| `App\Validation\ValidationResult` | `somnambulist/validation` `ErrorBag` |
| `App\Validation\Rules\Email` | Built-in `email` rule |
| `App\Validation\Rules\Length` | Built-in `between` / `min` / `max` rules |
| `App\Validation\Rules\EnumMember` | Built-in `in:value1,value2,...` rule |
| `App\Validation\Rules\IsoDate` | Carbon-based `callback` rule |
| Hand-rolled `fgetcsv` in `CsvTicketParser` | `League\Csv\Reader` |
| Hand-rolled CSV-injection guard | `League\Csv\EscapeFormula` |
| Raw `date()` / `DateTimeImmutable` in `SystemClock` | `Carbon::now()` / `CarbonInterface` |

## Layering Rules

- **Controllers** translate HTTP ↔ services. No business rules.
- **Services** orchestrate use cases (validate → build entity → classify → persist).
- **Repositories** are the persistence boundary. They speak `Database::query()` (Medoo
  under the hood) and return entities. No business rules. No HTTP.
- **Entities** are plain readonly value objects. No DB, no HTTP.
- **Parsers** turn raw bytes into normalized assoc rows. No persistence.
- **Validators** use `somnambulist/validation` `Factory` with Laravel-style rule strings.
  No side effects.

Naming rule: the storage technology (Medoo) is hidden behind `Database`. No class,
method, or identifier mentions "Medoo".

## File Layout

```
src/
├── app/
│   ├── Controllers/
│   │   └── TicketsController.php          # index, show, create, update, delete, import
│   ├── Entities/
│   │   ├── Ticket.php                     # readonly, fromRow()/toRow()
│   │   └── TicketMetadata.php             # nested VO
│   ├── Enums/
│   │   └── Ticket/                        # nested per-entity to avoid collisions
│   │       ├── Category.php               # backed enum
│   │       ├── Priority.php
│   │       ├── Status.php
│   │       ├── Source.php
│   │       └── DeviceType.php
│   ├── Filters/
│   │   └── TicketFilter.php               # category, priority, status, customer_id,
│   │                                      # assigned_to, q, limit, offset, sort
│   ├── Validation/
│   │   ├── TicketValidator.php            # uses somnambulist Factory->validate()
│   │   └── ValidationException.php        # thrown by services, carries field→message map
│   ├── Repositories/
│   │   └── TicketRepository.php           # uses Database::query()
│   ├── Parsers/
│   │   ├── TicketImportParserInterface.php   # parse(string $raw): iterable<array>
│   │   ├── ParserRegistry.php                # resolve by Content-Type / ?format=
│   │   ├── CsvTicketParser.php               # uses League\Csv\Reader + EscapeFormula
│   │   ├── JsonTicketParser.php
│   │   ├── XmlTicketParser.php               # XXE-safe (LIBXML_NONET, no entities)
│   │   └── ParseException.php
│   ├── Services/
│   │   ├── TicketService.php              # CRUD orchestration
│   │   ├── ImportService.php              # parse → validate → batch persist → summary
│   │   ├── ImportSummary.php              # total / successful / failed / errors[]
│   │   ├── Database.php                   # (existing) Medoo wrapper
│   │   ├── ContainerFactory.php           # (existing)
│   │   ├── Clock/
│   │   │   ├── ClockInterface.php         # now(): CarbonInterface
│   │   │   └── SystemClock.php            # returns Carbon::now()
│   │   ├── Ids/
│   │   │   ├── IdGeneratorInterface.php
│   │   │   └── UuidGenerator.php
│   │   └── Classification/
│   │       ├── ClassifierInterface.php
│   │       ├── ClassificationResult.php
│   │       └── NullClassifier.php         # T1 default; T2 swaps impl
│   ├── Http/
│   │   └── ErrorRenderer.php              # ValidationException→400, NotFound→404,
│   │                                      # generic 500 (no leakage)
│   ├── helpers.php                        # (existing)
│   └── routes.php                         # (existing) — add ticket routes
├── config/
│   ├── container.php                      # bind interfaces, register parsers, etc.
│   └── database.php
├── database/
│   └── schema.sql                         # tickets + ticket_logs
└── tests/
    ├── bootstrap.php                       # (existing)
    ├── Concerns/
    │   └── AppTestCase.php                 # (existing) — base for Feature tests
    ├── Unit/                               # isolated, fast, no HTTP
    │   ├── Entities/
    │   │   └── TicketTest.php
    │   ├── Filters/
    │   │   └── TicketFilterTest.php
    │   ├── Validation/
    │   │   └── TicketValidatorTest.php
    │   └── Parsers/
    │       ├── CsvTicketParserTest.php
    │       ├── JsonTicketParserTest.php
    │       └── XmlTicketParserTest.php
    ├── Feature/                            # full HTTP via Slim App::handle()
    │   ├── Health/
    │   │   └── HandshakeTest.php           # moved from tests/Health/
    │   └── Tickets/
    │       ├── CreateTicketTest.php
    │       ├── GetTicketTest.php
    │       ├── ListTicketsTest.php
    │       ├── UpdateTicketTest.php
    │       ├── DeleteTicketTest.php
    │       └── ImportTicketsTest.php
    ├── Integration/                        # reserved for Task 5 (workflows, concurrency)
    ├── Performance/                        # reserved for Task 5 (benchmarks)
    └── fixtures/
        ├── valid/
        │   ├── sample_tickets.csv          # 50 rows
        │   ├── sample_tickets.json         # 20 rows
        │   └── sample_tickets.xml          # 30 rows
        └── invalid/
            ├── malformed.csv
            ├── malformed.json
            ├── malformed.xml
            ├── xxe.xml                     # security regression
            └── csv_injection.csv           # security regression
```

### Test Layering Rules

| Folder | Boundary | Speed | Uses |
|---|---|---|---|
| `Unit/` | Single class, collaborators stubbed | ms | No `App`, no DB (or rare use of `:memory:` for repos only) |
| `Feature/` | Full HTTP request → response | tens of ms | `AppTestCase` (`get()`/`postJson()`/`postRaw()`) |
| `Integration/` (T5) | Multi-step workflows across services | hundreds of ms | `AppTestCase` |
| `Performance/` (T5) | Benchmarks under load | seconds | dedicated harness |

- Mirror production paths inside each test-type folder
  (e.g. `app/Parsers/CsvTicketParser.php` → `tests/Unit/Parsers/CsvTicketParserTest.php`).
- One assertion concern per test method; one class under test per file.
- Move existing `tests/Health/HandshakeTest.php` → `tests/Feature/Health/HandshakeTest.php`.
- Update `CLAUDE.md` testing paragraph to reflect the `Unit/` + `Feature/` split.

## Schema (T1 + T2-ready)

```sql
CREATE TABLE IF NOT EXISTS tickets (
    id                        TEXT PRIMARY KEY,
    customer_id               TEXT NOT NULL,
    customer_email            TEXT NOT NULL,
    customer_name             TEXT NOT NULL,
    subject                   TEXT NOT NULL,
    description               TEXT NOT NULL,
    category                  TEXT NOT NULL,
    priority                  TEXT NOT NULL,
    status                    TEXT NOT NULL DEFAULT 'new',
    assigned_to               TEXT,
    tags                      TEXT NOT NULL DEFAULT '[]',   -- JSON array
    metadata_source           TEXT,
    metadata_browser          TEXT,
    metadata_device_type      TEXT,
    classification_confidence REAL,                          -- T2
    classification_reasoning  TEXT,                          -- T2
    classification_keywords   TEXT,                          -- T2 (JSON)
    created_at                TEXT NOT NULL,
    updated_at                TEXT NOT NULL,
    resolved_at               TEXT
);
CREATE INDEX IF NOT EXISTS idx_tickets_status     ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_category   ON tickets(category);
CREATE INDEX IF NOT EXISTS idx_tickets_priority   ON tickets(priority);
CREATE INDEX IF NOT EXISTS idx_tickets_customer   ON tickets(customer_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at);

CREATE TABLE IF NOT EXISTS ticket_logs (             -- T2 audit
    id         TEXT PRIMARY KEY,
    ticket_id  TEXT NOT NULL,
    event      TEXT NOT NULL,                        -- e.g. 'classified', 'manual_override'
    payload    TEXT,                                 -- JSON
    created_at TEXT NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);
```

## Service vs Repository (responsibility split)

| | Repository | Service |
|---|---|---|
| Concern | Persistence | Use-case orchestration |
| Knows about | Tables, columns, hydration | Validation, classifier, summaries |
| Returns | Entities (or null) | Entities, DTOs, `ImportSummary` |
| Example | `TicketRepository::findAll(TicketFilter): array<Ticket>` | `TicketService::create(array $input, bool $autoClassify): Ticket` |

`TicketRepository` is concrete (no interface) — single backend, `Database` already
abstracts the driver, tests use `:memory:` SQLite through the same path.

## Endpoint Contracts

- `POST /tickets` — JSON body, `201` + ticket. Optional `?auto_classify=1` (no-op T1).
- `POST /tickets/import` — raw body, parser selected by `Content-Type` (or `?format=`
  fallback). `200` with `{total, successful, failed, errors:[{row, field, message, raw}]}`.
  Partial success allowed.
- `GET /tickets` — filters: `category`, `priority`, `status`, `assigned_to`,
  `customer_id`, `q` (subject/description), `limit` (default 50, max 200), `offset`,
  `sort`. `200`.
- `GET /tickets/:id` — `200` or `404`.
- `PUT /tickets/:id` — partial update, validated. `200` or `404`.
- `DELETE /tickets/:id` — `204` or `404`.

## Validation Rules

Uses `somnambulist/validation` `Factory` with Laravel-style rule strings.
Enum values are inlined via `in:` rule (built from `Enum::cases()`).
Nested fields use dot notation (`metadata.source`).

```php
// TicketValidator::rulesForCreate()
[
    'customer_id'          => 'required',
    'customer_email'       => 'required|email',
    'customer_name'        => 'required',
    'subject'              => 'required|string|between:1,200',
    'description'          => 'required|string|between:10,2000',
    'category'             => 'required|in:account_access,technical_issue,billing_question,feature_request,bug_report,other',
    'priority'             => 'required|in:urgent,high,medium,low',
    'status'               => 'in:new,in_progress,waiting_customer,resolved,closed',
    'metadata.source'      => 'in:web_form,email,api,chat,phone',
    'metadata.device_type' => 'in:desktop,mobile,tablet',
    'tags'                 => 'array',
    'tags.*'               => 'string|max:50',
    'created_at'           => 'callback',  // Carbon::parse() in callback
    'updated_at'           => 'callback',
    'resolved_at'          => 'callback',
]
```

Error shape (field→message map):
`400 {"error": "Validation failed", "details": {"customer_email": "...", "subject": "..."}}`

`ValidationException` carries `array<string, string>` from
`$validation->errors()->firstOfAll(':message', true)`.

## Security Defaults (CHECKPOINTS.md alignment)

- XML parser: `libxml_set_external_entity_loader(fn() => null)` + `LIBXML_NONET`,
  reject DTDs.
- CSV parser: `League\Csv\EscapeFormula` handles `= + - @ \t \r` prefixes.
- All queries via `Database::query()` (PDO prepared statements under the hood).
- `displayErrorDetails: false` outside debug; generic 500 body.

## Container Bindings (config/container.php additions)

```php
use Somnambulist\Components\Validation\Factory as ValidationFactory;

ClockInterface::class       => DI\autowire(SystemClock::class),
IdGeneratorInterface::class => DI\autowire(UuidGenerator::class),
ClassifierInterface::class  => DI\autowire(NullClassifier::class),

ValidationFactory::class => function (): ValidationFactory {
    return new ValidationFactory();
},

ParserRegistry::class => function (ContainerInterface $c): ParserRegistry {
    return new ParserRegistry([
        'text/csv'         => $c->get(CsvTicketParser::class),
        'application/json' => $c->get(JsonTicketParser::class),
        'application/xml'  => $c->get(XmlTicketParser::class),
        'text/xml'         => $c->get(XmlTicketParser::class),
    ]);
},
```

## Implementation Order

1. Install deps (`somnambulist/validation`, `league/csv`, `nesbot/carbon`)
2. Enums, `Ticket` entity / `TicketMetadata` entity
3. Schema + `TicketRepository`
4. `Clock` (returns `CarbonInterface`) / `Id` abstractions + container bindings
5. `TicketValidator` (somnambulist rules), `ValidationException`, `ErrorRenderer` wired
   into Slim error middleware
6. `TicketService` (CRUD) + `TicketsController` CRUD actions + routes
7. `TicketImportParserInterface` + 3 parsers (`CsvTicketParser` via league/csv) +
   `ParserRegistry`
8. `ImportService` + `/tickets/import` action
9. `NullClassifier` + plumbing through `TicketService::create()`
10. Fixtures (`sample_tickets.{csv,json,xml}` + invalid variants)
11. Happy-path tests per area; `make phpunit` green; `make test` smoke OK
