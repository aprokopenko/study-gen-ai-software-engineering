# AI Interaction Report — Project Setup

## Round 1: Initial Request (Kiro CLI)

**Asked:** Initialize a PHP REST API project with Slim, SQLite, Medoo, running in Docker with docker-compose. Nginx as webserver. Makefile for shortcuts. Start with one hello world route.

**Proposal:** Two Docker containers (Nginx + PHP-FPM), bind-mounted `src/` for live editing, `docker-compose.yml`, Makefile with common shortcuts, single `GET /` route.

**Clarifications requested:**
- PHP version → PHP 8.5 (latest stable, released Nov 2025)
- Webserver → Nginx + PHP-FPM as separate containers
- Live editing → confirmed bind-mount approach

**Further refinements before implementation:**
- SQLite persistence → bind-mount `./data/` (visible on host, survives restarts)
- File structure → expandable from the start: routes, controllers, services, middleware, config
- Makefile commands → all app commands run inside the container via `docker compose exec`
- HOWTORUN.md → deferred, not part of this setup

**Result:** Full project scaffold created and verified working. `make test` returned `{"message":"Hello, World!"}`.

---

## Round 2: Fixes & Improvements

**Asked:** Four issues to address:

| # | Issue | Solution |
|---|-------|----------|
| 1 | `make test` requires `python3` | Replaced with `curl -i` — no external tools needed |
| 2 | PHP files missing strict types | Added `declare(strict_types=1)` to all PHP files |
| 3 | Config loading needs Laravel-style `config()` helper | Created `config('file.key', $default)` helper in `app/helpers.php`, autoloaded via `composer.json` |
| 4 | Services should use PHP-DI with `::make()` pattern | Installed `php-di/php-di`, created `ContainerFactory`, wired container into Slim, added `::make()` to `Database` |

---

## Round 3: Naming

**Asked:** Rename `getDb()` method on `Database` service.

**Options offered:** `builder()`, `connection()`, `query()`

**Chosen:** `query()` → `Database::make()->query()->select(...)`

---

## Round 4: Structural Refinements

**Asked:** Three more edits:

| # | Issue | Solution |
|---|-------|----------|
| 1 | `container.php` should use explicit callable style like `routes.php` | Rewrote to `function (ContainerBuilder $builder): void` |
| 2 | Move `ContainerFactory` to `Services/` | Moved to `App\Services\ContainerFactory` |
| 3 | Add `container()` global helper | Added `container(ClassName::class)` to `helpers.php` |

---

## Final File Structure

```
homework-1/
├── docker-compose.yml
├── Makefile
├── .gitignore
├── data/                              # SQLite DB (bind-mount, persists restarts)
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile                 # php:8.5-fpm + libsqlite3-dev + unzip + composer
└── src/
    ├── composer.json                  # slim/slim, slim/psr7, catfan/medoo, php-di/php-di
    ├── public/index.php               # Entry point: bootstrap container + Slim, run
    ├── config/
    │   ├── database.php               # SQLite path config
    │   └── container.php             # PHP-DI bindings (callable style)
    └── app/
        ├── routes.php                 # Route definitions (callable style)
        ├── helpers.php                # config() and container() global helpers
        ├── Controllers/
        │   └── HomeController.php     # GET / → {"message": "Hello, World!"}
        ├── Services/
        │   ├── ContainerFactory.php   # Builds/caches PHP-DI container
        │   └── Database.php          # Medoo wrapper with ::make() and ->query()
        └── Middleware/                # Empty, ready for future use
```

## Key Patterns Established

```php
// Config access
config('database.type');           // dot-notation
config('database.missing', 'x');   // with default

// DI resolution
container(SomeService::class);     // global helper
SomeService::make();               // static shorthand on service classes

// Database usage
Database::make()->query()->select('table', '*');
```

## Makefile Commands

| Command | Action |
|---------|--------|
| `make up` | Build images and start containers |
| `make down` | Stop containers |
| `make restart` | down + up |
| `make logs` | Follow container logs |
| `make shell` | Bash inside PHP container |
| `make composer args="..."` | Run composer inside container |
| `make test` | `curl -i localhost:3000/` |
