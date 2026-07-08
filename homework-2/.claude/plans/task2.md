# Task 2 — Auto-Classification

## Context

T1 reserved seams for this work: `ClassifierInterface` is injected into
`TicketService::create()` with a `NullClassifier` default, the `tickets` table has
`classification_confidence/reasoning/keywords` columns, and the `ticket_logs` audit table
already exists. T2 fills those seams with a real rule-based classifier, makes
`category`/`priority` optional with sane defaults, adds the explicit
`POST /tickets/{id}/auto-classify` endpoint, and writes a decision log per classification.

User-confirmed scope:

1. Make `category` and `priority` optional on create; default to `other` and `medium`.
   `customer_id`, `customer_email`, `customer_name`, `subject`, `description` stay required.
2. When `auto_classify=true` and the user supplied `category`/`priority`, **user values
   win** — the classifier still runs and records confidence/reasoning/keywords. (Spec:
   "Allow manual override".)
3. `POST /tickets/{id}/auto-classify` always overwrites — manual override does not apply
   when the user explicitly asks for re-classification.
4. Tests are deferred to a follow-up phase. This phase ships HTTP-client examples for
   manual verification only.

## Contract Change: `ClassificationResult`

The current contract returns only `{confidence, reasoning, keywords}` — it cannot
suggest a category or priority, which contradicts the T2 spec. Extend the DTO
(additive, only `NullClassifier` produces it today):

```php
readonly class ClassificationResult
{
    public function __construct(
        public Category $suggestedCategory,
        public Priority $suggestedPriority,
        public float    $confidence,
        public string   $reasoning,
        public array    $keywords,    // ordered, unique matched terms
    ) {}
}
```

`NullClassifier` returns `(Category::Other, Priority::Medium, 0.0, '', [])`. Keep it as
a safe fallback for environments / tests that want classification disabled.

## Keyword Map (config-driven)

New file: `src/config/classification.php`. Loaded via the existing `config()` helper.
Tweakable without touching the classifier class.

```php
return [
    'categories' => [
        'account_access'   => ['login','password','2fa','sign in','locked out'],
        'technical_issue'  => ['error','crash','freeze','broken'],
        'billing_question' => ['invoice','payment','refund','charge','billing'],
        'feature_request'  => ['feature','suggestion','would be nice','please add'],
        'bug_report'       => ['bug','defect','reproduce','steps to reproduce'],
    ],
    'priorities' => [
        'urgent' => ["can't access",'critical','production down','security'],
        'high'   => ['important','blocking','asap'],
        'low'    => ['minor','cosmetic','suggestion'],
    ],
];
```

## `KeywordClassifier`

`src/app/Services/Classification/KeywordClassifier.php`

- Constructor: `__construct(array $categoryKeywords, array $priorityKeywords)` — DI
  factory wires the maps from `config('classification.*')`.
- `classify(Ticket $ticket): ClassificationResult`
  - Lowercase haystack = `strtolower($ticket->subject . ' ' . $ticket->description)`.
  - For each category map entry, count `str_contains` hits. Pick the category with the
    highest count; ties broken by map iteration order; zero hits → `Category::Other`.
  - Same logic for priority; zero hits → `Priority::Medium`.
  - `keywords` = ordered unique matches across both maps.
  - `confidence = min(1.0, count(matched_keywords) / 3)`.
  - `reasoning` is a short human string, e.g.
    `"Matched account_access via [login, password]; priority=urgent via [critical]"`,
    or `"No keywords matched; defaulted to other/medium"` when nothing matches.

## Logging Architecture

Keep `TicketService` clean — introduce a dedicated logger so audit writes don't pollute
business logic.

| File | Role |
|---|---|
| `src/app/Repositories/TicketLogRepository.php` (new) | Minimal `insert(array $row)` against `ticket_logs`. Mirrors `TicketRepository`'s `Database`-injected style. |
| `src/app/Services/Classification/ClassificationLogger.php` (new) | `log(string $ticketId, ClassificationResult $r): void`. Composes `TicketLogRepository`, `IdGeneratorInterface`, `ClockInterface`. Writes one row with `event='classify'` and JSON-encoded `payload = {confidence, reasoning, keywords, suggested_category, suggested_priority}`. |

`ClassificationLogger` injected into `TicketService` (autowired).

## Validation Change

`src/app/Validation/TicketValidator.php` — in `validateCreate()` drop `required|` from
`category` and `priority`; keep the `in:` constraint so garbage values are still
rejected when supplied. `validateUpdate()` is already optional.

## `TicketService` Changes

`src/app/Services/TicketService.php`

In `create($input, $autoClassify = false)`:

1. **Capture override flags** before mutating input:
   `$categoryProvided = !empty($input['category']);` (same for priority).
2. **Apply defaults**: `$input['category'] ??= Category::Other->value;` (same for priority).
3. Run validator (now passes when category/priority were originally absent).
4. Build the initial `Ticket` as today (existing lines 51-71).
5. If `$autoClassify`:
   - `$result = $this->classifier->classify($ticket)`.
   - Build replacement `Ticket`: overwrite `category` only if `!$categoryProvided`,
     `priority` only if `!$priorityProvided`; **always** set
     `classificationConfidence/Reasoning/Keywords`.
6. `$this->repository->insert($ticket)`.
7. If `$autoClassify`: `$this->logger->log($ticket->id, $result)` **after** insert (FK
   on `ticket_logs.ticket_id` requires the parent row to exist).

New method `autoClassify(Ticket $ticket): ClassificationResult`:

- Run classifier.
- Build replacement `Ticket` overwriting `category`, `priority`, all three
  classification fields, and `updatedAt = clock->now()`. **No** override-preservation —
  this endpoint is the user explicitly asking for re-classification.
- Persist via existing `TicketRepository::update`.
- Log.
- Return the `ClassificationResult` (controller shapes it).

## Controller + Route

`src/app/Controllers/TicketsController.php` — add

```php
public function autoClassify(Request $request, Response $response, array $args): Response
```

mirroring `show()`'s `findOrFail` + `HttpNotFoundException` pattern. Returns the
focused payload `{category, priority, confidence, reasoning, keywords}` per the T2
spec response shape.

While in this file: fix the boolean parsing in `create()` (line 42). Currently
`(bool) "false" === true`; replace with
`filter_var($params['auto_classify'] ?? false, FILTER_VALIDATE_BOOLEAN)`.

`src/app/routes.php` — add

```php
$app->post('/tickets/{id}/auto-classify', [TicketsController::class, 'autoClassify']);
```

before the generic `/tickets/{id}` routes (defensive ordering, same convention as
`/tickets/import`).

## Container Binding

`src/config/container.php` line 31 — replace

```php
ClassifierInterface::class => \DI\autowire(NullClassifier::class),
```

with a closure factory (arrays can't be autowired):

```php
ClassifierInterface::class => function (): ClassifierInterface {
    return new KeywordClassifier(
        config('classification.categories'),
        config('classification.priorities'),
    );
},
```

## File Layout (delta vs T1)

```
src/
├── app/
│   ├── Controllers/
│   │   └── TicketsController.php             # + autoClassify() action; fix bool parsing
│   ├── Repositories/
│   │   └── TicketLogRepository.php           # NEW — insert() into ticket_logs
│   ├── Services/
│   │   ├── TicketService.php                 # defaults, override flags, logger, autoClassify()
│   │   └── Classification/
│   │       ├── ClassificationResult.php      # + suggestedCategory, suggestedPriority
│   │       ├── ClassificationLogger.php      # NEW
│   │       ├── KeywordClassifier.php         # NEW
│   │       └── NullClassifier.php            # returns Other/Medium defaults
│   ├── Validation/
│   │   └── TicketValidator.php               # drop required| from category, priority
│   └── routes.php                            # + POST /tickets/{id}/auto-classify
├── config/
│   ├── classification.php                    # NEW — keyword maps
│   └── container.php                         # bind KeywordClassifier
└── ../http-client/
    └── tickets.http                          # + auto-classify examples
```

No schema changes needed — `tickets.classification_*` and `ticket_logs` are already in
T1's `schema.sql`.

## HTTP-Client Examples (manual verification)

Append to `http-client/tickets.http`:

1. **Create with defaults** — POST without `category`/`priority`, no flag → 201,
   response shows `category=other`, `priority=medium`, `classification.confidence=null`.
2. **Auto-classify on create** — POST `?auto_classify=true` with subject/description
   containing keywords like `login` + `critical`, no category/priority → 201, response
   shows `category=account_access`, `priority=urgent`, classification block populated.
3. **Manual override** — POST `?auto_classify=true` with explicit `category=other` and
   `priority=low` but a keyword-rich body → 201, `category=other`/`priority=low` are
   kept, but `classification.keywords` and `confidence` are populated.
4. **Re-classify endpoint** — `POST /tickets/{{ticketId}}/auto-classify` against a
   neutral ticket created earlier → 200 with the focused payload; follow with
   `GET /tickets/{{ticketId}}` to confirm overwrite.
5. **404** — `POST /tickets/does-not-exist/auto-classify` → 404.

To inspect log writes manually: `make shell` then
`sqlite3 data/support.sqlite "select * from ticket_logs"`.

## Implementation Order

1. Extend `ClassificationResult`; update `NullClassifier` to the new shape.
2. Drop `required|` from `category`/`priority` in `TicketValidator`.
3. Add `src/config/classification.php`; build `KeywordClassifier`; bind it in
   `config/container.php`.
4. Add `TicketLogRepository`; add `ClassificationLogger`.
5. Update `TicketService::create()` (override flags + defaults + logger call) and add
   `TicketService::autoClassify()`.
6. Add `TicketsController::autoClassify()` (and fix the `(bool)` bug in `create()`);
   register the route.
7. Append HTTP-client examples; smoke-check end-to-end via `make up` + `make test` and
   the new requests in `tickets.http`.

## Tests — Deferred

Tests for the classifier, the new endpoint, defaults, manual override, and log writes
are deliberately out of scope for this phase; they will be authored in a dedicated
test-suite phase covering all of Task 2 (and aligning with T1's `Unit/`/`Feature/`
split). Verification this phase is manual via `tickets.http`.

## Risks / Watch-outs

- **`(bool) "false"` is `true`** — the existing `auto_classify` parsing at
  `TicketsController::create()` line 42 is buggy. Switch to `FILTER_VALIDATE_BOOLEAN`.
- **FK ordering** — `ticket_logs.ticket_id REFERENCES tickets(id) ON DELETE CASCADE`.
  Always insert the ticket first, then the log row.
- **`ticket_logs.payload` is `TEXT`** — store JSON-encoded string, not a nested array
  (Medoo's array-handling for `TEXT` columns is not what we want here).
- **Contract ripple** — only `NullClassifier` produces `ClassificationResult` today;
  grep before editing to confirm no other call site sneaks in.
- **Default-before-validate ordering** — defaults are applied **before** validation so
  the `in:` constraint still rejects a user-supplied garbage value while accepting an
  absent one.
