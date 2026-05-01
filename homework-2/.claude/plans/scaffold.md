Create a new PHP REST API project with the following infrastructure and conventions.

## Technology Stack
- PHP 8.5 (strict_types=1 in all files)
- Slim Framework 4 (PSR-7/15)
- PHP-DI 7 (dependency injection with autowiring)
- Medoo 2.2 (query builder over PDO)
- SQLite (file-based, path configurable via DATABASE_PATH env var)
- PHPUnit 11 (testing)
- Docker: PHP 8.5-FPM + Nginx Alpine
- Ramsey UUID 4 (UUID v4 generation)

## Project Structure
Place all PHP code under src/, docker config under docker/, HTTP examples under http-client/.
src/
├── public/index.php          ← entry point, creates Slim app from bootstrap
├── bootstrap.php             ← initializes DI container and Slim app
├── composer.json
├── phpunit.xml
├── app/
│   ├── helpers.php           ← config() and container() global helpers
│   ├── routes.php            ← all route definitions
│   ├── Controllers/
│   │   └── AbstractController.php  ← base class with json() response helper
│   ├── Repositories/         ← DB query classes
│   ├── Services/
│   │   ├── ContainerFactory.php    ← PHP-DI singleton factory
│   │   └── Database.php            ← Medoo wrapper with migration runner
│   └── Middleware/
├── config/
│   ├── database.php          ← ['database_type' => 'sqlite', 'database_file' => ...]
│   └── container.php         ← PHP-DI bindings (Medoo instance setup)
├── database/
│   └── schema.sql            ← raw SQL table definitions and indexes
├── bin/
│   └── setup.php             ← runs schema.sql migrations on startup
└── tests/
├── bootstrap.php
└── Concerns/
└── AppTestCase.php   ← base test: in-memory SQLite, container reset, get()/postJson() helpers
docker/
├── php/Dockerfile            ← FROM php:8.5-fpm, install pdo_sqlite + Composer
└── nginx/default.conf        ← port 80, root /var/www/html/public, fastcgi to php:9000
http-client/
├── api.http                  ← example requests (JetBrains/REST Client format)
└── http-client.env.json      ← {"dev": {"baseUrl": "http://localhost:3000"}}

## Docker Compose
Two services: php (builds from docker/php) and nginx (nginx:alpine).
- php mounts: ./src:/var/www/html and ./data:/var/www/data
- nginx mounts: ./docker/nginx/default.conf and ./src/public
- nginx exposes port 3000:80
- Both services on the same network

## Makefile Targets
setup    → docker compose build + up + composer install + run bin/setup.php + fix permissions
up       → docker compose up --build -d
down     → docker compose down
restart  → down + up
logs     → docker compose logs -f
shell    → docker exec -it <php-container> bash
composer → docker exec <php-container> composer $(args)
phpunit  → docker exec <php-container> ./vendor/bin/phpunit
test     → curl -s http://localhost:3000/ (health check smoke test)

## Routing Convention
- Define all routes in app/routes.php as a function that receives the Slim App instance
- Controllers are classes with public action methods receiving (Request, Response, array $args)
- JSON response helper in AbstractController: $this->json($response, $data, $statusCode)

## Testing Convention
- AppTestCase sets DATABASE_PATH=:memory: and resets the DI container for each test
- Helpers: get($path), postJson($path, $data), postRaw($path, $body, $contentType)
- seedTransaction(array $overrides = []) helpe[scaffold.md](scaffold.md)r for seeding test data
- Test files mirror the controlle[scaffold.md](scaffold.md)r structure (tests/Accounts/, tests/Transactions/, etc.)

## Error Response Format
Validation errors → 400:
{"error": "Validation failed", "details": [{"field": "fieldName", "message": "..."}]}
Not found → 404: {"error": "Not found"}

## What to build
Start by scaffolding the full directory structure, docker-compose.yml, Makefile, Dockerfiles,
Nginx config, composer.json, bootstrap.php, helpers, container config, database config,
and an AppTestCase base. The application itself will be added later