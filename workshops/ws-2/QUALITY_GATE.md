# Quality Gate Plan — Banking Transaction Parser

## Tools (all stdlib-compatible or zero-config)

| Tool | Purpose | Install |
|---|---|---|
| `pytest` | unit + integration tests | `pip install pytest` |
| `pytest-cov` | coverage measurement | `pip install pytest-cov` |
| `ruff` | linting + style (replaces flake8/isort/pyupgrade) | `pip install ruff` |
| `mypy` | static type checking | `pip install mypy` |

---

## Gate 1 — Static Analysis (must pass before any commit)

**Linting:** `ruff check .`
- No unused imports
- No undefined names
- No bare `except:` clauses
- Consistent string quotes

**Type checking:** `mypy transaction_parser/ main.py --strict`
- All function signatures typed (already mostly done)
- No implicit `Any`
- `Optional` fields explicit

Thresholds: **0 errors, 0 warnings**

---

## Gate 2 — Unit Tests (per module)

### `models.py`
- [ ] `Transaction` parses all 5 timestamp formats correctly
- [ ] `Transaction` raises `ValueError` on invalid timestamp
- [ ] `Transaction` converts `int`, `float`, `str` amount to `Decimal`
- [ ] `Transaction` raises on invalid Decimal (e.g. `"abc"`)
- [ ] `FraudAlert.__str__` format is correct

### `parsers.py`
- [ ] `CSVParser` parses valid CSV with all required fields
- [ ] `CSVParser` handles field aliases (`txn_id`, `date`, `sum`, etc.)
- [ ] `CSVParser` records error on missing required field, continues parsing
- [ ] `JSONParser` parses array format
- [ ] `JSONParser` parses `{"transactions": [...]}` object format
- [ ] `JSONParser` records error on invalid JSON
- [ ] `XMLParser` parses `<transaction>` tags
- [ ] `XMLParser` parses `<txn>` tags
- [ ] `XMLParser` records error on malformed XML
- [ ] `auto_parse` detects CSV by `.csv` extension
- [ ] `auto_parse` detects JSON by content sniff (`[` / `{`)
- [ ] `auto_parse` detects XML by content sniff (`<`)

### `fraud_detector.py`
- [ ] `LARGE_TRANSACTION`: amount = 9999 → no alert
- [ ] `LARGE_TRANSACTION`: amount = 10000 → HIGH alert
- [ ] `LARGE_TRANSACTION`: amount = 20000 → CRITICAL alert
- [ ] `UNUSUAL_HOURS`: hour = 2 → LOW alert; hour = 9 → no alert
- [ ] `ROUND_AMOUNT`: 1000 → alert; 999 → no alert; 1500 → no alert
- [ ] `DUPLICATE_TRANSACTION`: same amount+merchant+account within 5 min → HIGH alert
- [ ] `DUPLICATE_TRANSACTION`: same but 6 min apart → no alert
- [ ] `HIGH_VELOCITY`: 5 txns from same account within 10 min → HIGH alert
- [ ] `HIGH_VELOCITY`: 4 txns → no alert
- [ ] `STRUCTURING`: 3 txns in $8500–$9999 range within 60 min → CRITICAL alert
- [ ] `STRUCTURING`: only 2 such txns → no alert
- [ ] `ACCOUNT_MISMATCH`: `account_from == account_to` → MEDIUM alert
- [ ] `FraudDetector.analyze` returns correct mapping of `txn_id → alerts`
- [ ] `FraudDetector.summary` counts by severity and rule correctly

---

## Gate 3 — Integration Tests

- [ ] Parse `data/sample.csv` → expected transaction count, no parse errors
- [ ] Parse `data/sample.json` → expected transaction count
- [ ] Parse `data/sample.xml` → expected transaction count
- [ ] Full pipeline: parse CSV → analyze → summary has correct flagged count
- [ ] `auto_parse` on each sample file returns correct format in `ParseResult`
- [ ] CLI `demo` mode exits with code 0
- [ ] CLI with nonexistent file exits with code 1

---

## Gate 4 — Coverage

Run: `pytest --cov=transaction_parser --cov-report=term-missing`

Thresholds:
- `models.py` → **100%**
- `parsers.py` → **≥ 90%**
- `fraud_detector.py` → **≥ 95%** (each rule must be exercised)
- Overall → **≥ 90%**

---

## Gate 5 — Edge Cases (regression targets)

- [ ] CSV with Windows line endings (`\r\n`)
- [ ] CSV with extra whitespace in headers
- [ ] JSON with `null` optional fields (`location`, `description`)
- [ ] XML with attributes instead of child elements
- [ ] Transaction with `amount = 0`
- [ ] Empty file → `ParseResult` with 0 transactions, no crash
- [ ] Single transaction file → no `HIGH_VELOCITY` false positive
- [ ] Two identical transactions → both flagged as `DUPLICATE_TRANSACTION` (verify this is intended)

---

## Suggested file structure

```
workshop2/
├── tests/
│   ├── test_models.py
│   ├── test_parsers.py
│   ├── test_fraud_detector.py
│   └── test_integration.py
├── pyproject.toml          # ruff + mypy + pytest config
```

---

## `pyproject.toml` config

```toml
[tool.pytest.ini_options]
testpaths = ["tests"]

[tool.coverage.report]
fail_under = 90

[tool.ruff]
target-version = "py310"
select = ["E", "F", "I", "UP"]

[tool.mypy]
strict = true
python_version = "3.10"
```
