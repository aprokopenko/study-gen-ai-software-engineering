# Task 1 — Docker Environment & Developer Tooling

**Date:** 2026-06-23

## Research / Decisions

### PHP base image
- **context7 query 1:** Searched `PHP` → `/serversideup/docker-php` (High reputation).
  Docs showed `serversideup/php:8.5-cli` in examples. However, the official Docker Hub
  `php:8.4-cli` image is preferred:
  - PHP 8.4 is the confirmed current stable release (GA: November 2024).
  - Official image works cleanly with `user: "${UID}:${GID}"` in docker compose.
  - serversideup images default to `www-data` user and require switching, which conflicts
    with the UID/GID pass-through required by the Makefile.
- **Decision:** `FROM php:8.4-cli` — confirmed running as `PHP 8.4.22` inside container.

### Coverage driver
- **context7 query 2:** Searched `PHPUnit` → `/websites/phpunit_de_en_12_5` (High reputation,
  1455 snippets). Docs confirm:
  - PHPUnit 12 requires PHP 8.3+; PHPUnit 12.5.30 installed.
  - pcov is listed first and is the lightweight coverage-only driver (vs xdebug which adds
    debugging overhead); preferred for CI use.
- **Decision:** `pecl install pcov` + `docker-php-ext-enable pcov`. Enabled at runtime with
  `-d pcov.enabled=1` (already in the Makefile `coverage` target).

### Money/decimal extensions
- **context7 query 3:** Queried `/serversideup/docker-php` for extension install patterns.
  `bcmath` is the standard PHP arbitrary-precision extension; `gmp` provides big-integer
  arithmetic as complement.
- **Decision:** Both installed via `docker-php-ext-install bcmath gmp`.

## Files Created

| File                  | What / Why                                                      |
|-----------------------|-----------------------------------------------------------------|
| `Dockerfile`          | php:8.4-cli base, installs bcmath, gmp, pcov, Composer v2      |
| `docker-compose.yml`  | `app` service, bind-mounts `.:/app`, runs as `${UID}:${GID}`   |
| `.dockerignore`       | Excludes vendor/, shared/, docs/, .git/, .claude/, coverage.xml |
| `composer.json`       | Minimal valid; PSR-4 autoload (src/ → BankingPipeline\); PHPUnit ^12.5 |
| `research-notes.md`   | Cumulative library decision ledger (Task 1 entries added)       |
| `context/task-1-docker-environment.md` | This file                                      |

## Files Not Modified

- `Makefile` — already complete with all required targets (`build`, `install`, `run`,
  `validate`, `test`, `coverage`, `mcp`, `shell`, plus cleanup). No changes needed;
  service name `app` and pcov flags all match the Dockerfile.
- `.mcp.json` — already correct; `pipeline-status` command uses `docker compose run --rm -T app`.

## Self-Verification

All commands run inside Docker via `make` targets — no PHP on the host.

### `make build` — SUCCESS
```
docker compose build
...
Image homework-6-app Built
```
PHP 8.4-cli image built with bcmath, gmp, pcov, Composer 2.

### `make install` — SUCCESS
```
docker compose run --rm app composer install --no-interaction --prefer-dist
...
Installing phpunit/phpunit (12.5.30)
...
25 packages [============================] 100%
Generating optimized autoload files
```
PHPUnit 12.5.30 and all 25 transitive dependencies installed.

### PHP version check — `docker compose run --rm app php -v`
```
PHP 8.4.22 (cli) (built: Jun 11 2026 00:26:13) (NTS)
Zend Engine v4.4.22 with Zend OPcache v8.4.22
```
Confirmed: PHP 8.4.22 running inside the container.

### Extension check — `docker compose run --rm app php -m | grep -iE "pcov|bcmath|gmp"`
```
bcmath
gmp
pcov
```
Confirmed: all three extensions loaded.

### UID/GID mapping — container writes are host-user-owned
```
$ docker compose run --rm app bash -c "echo test > /app/shared/uid-test.txt"
$ ls -la shared/uid-test.txt
-rw-r--r-- 1 alex alex 5 чер 23 15:08 shared/uid-test.txt
```
Confirmed: file written by the container is owned by host user `alex` — no sudo needed to delete.

## Outcome

All Task 1 acceptance criteria met:
- `make build` succeeds.
- `make install` succeeds.
- Pinned PHP 8.4.22 confirmed in container.
- pcov, bcmath, gmp all loaded.
- UID/GID pass-through works — container writes are host-user-owned.
- Makefile not overwritten — all targets (`build`, `install`, `run`, `validate`, `test`,
  `coverage`, `mcp`, `shell`) were already present and correct.
