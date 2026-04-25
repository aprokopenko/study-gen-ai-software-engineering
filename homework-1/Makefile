.PHONY: up down restart logs shell composer test

up:
	docker compose up -d --build

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f

shell:
	docker compose exec php bash

composer:
	docker compose exec php composer $(args)

test:
	curl -i http://localhost:3000/
