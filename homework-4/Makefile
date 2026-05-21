.PHONY: build up down test lint shell logs restart install

build:
	@test -f .env || cp .env.example .env
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

test:
	docker compose run --rm app npm test

lint:
	docker compose run --rm app npx eslint src/

shell:
	docker compose run --rm app sh

logs:
	docker compose logs -f

restart: down up
