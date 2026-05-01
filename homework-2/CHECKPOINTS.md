# Quality & Security Verification Checkpoints

Run these checks once the application is feature-complete.

---

## 1. Static Analysis — PHPStan (level 8+)

```bash
composer require --dev phpstan/phpstan
```

`phpstan.neon`:
```neon
parameters:
    level: 8
    paths:
        - app
```

Run: `vendor/bin/phpstan analyse`

- [ ] Zero errors at level 8

---

## 2. Dependency Vulnerability Audit

```bash
composer audit
```

- [ ] No known vulnerabilities in dependencies

---

## 3. PHPUnit Coverage (>85%)

```bash
vendor/bin/phpunit --coverage-html reports/coverage
```

Requires PCOV or Xdebug in the Docker image.

- [ ] Overall coverage >85%
- [ ] Unit tests: validators, classifier logic, model hydration
- [ ] Integration tests: full HTTP request/response cycle via `App::handle()`
- [ ] Performance tests: bulk import benchmarks

---

## 4. Input Validation & SQL Injection Prevention

Medoo uses PDO prepared statements. Verify with adversarial test data:

- [ ] Test with SQL injection payloads (`'; DROP TABLE--`) — assert no effect
- [ ] Test with XSS payloads (`<script>alert(1)</script>`) — assert stored/returned safely
- [ ] Test with oversized inputs beyond field limits — assert 400 response
- [ ] All enums validated server-side: category, priority, status, source, device_type
- [ ] File uploads: MIME type checked, size limited, path traversal in filenames rejected

---

## 5. XXE Prevention (XML Import)

Highest security risk — XML parsing must not expand external entities.

- [ ] XML loaded with `LIBXML_NOENT | LIBXML_NONET` flags
- [ ] Test: submit XML with `<!ENTITY>` declaration — assert rejected or entities not expanded
- [ ] No external DTD loading

---

## 6. CSV Injection Prevention

Cells starting with `=`, `+`, `-`, `@`, `\t`, `\r` can execute as formulas in spreadsheet software.

- [ ] Imported values with dangerous prefixes are sanitized or escaped
- [ ] Test: import CSV with formula payloads — assert safe storage

---

## 7. Error Handling — No Information Leakage

- [ ] Production error responses never expose stack traces, file paths, or SQL queries
- [ ] 500 errors return generic `{"error": "Internal server error"}`
- [ ] Slim error middleware: `displayErrorDetails: false` in production
- [ ] Test: trigger a server error — assert response body contains no internals

---

## 8. Automated CI Target

```makefile
make ci:
	vendor/bin/phpstan analyse
	composer audit
	vendor/bin/phpunit --coverage-text --coverage-clover reports/clover.xml
```

- [ ] Single command runs all checks
- [ ] All checks pass with zero errors
