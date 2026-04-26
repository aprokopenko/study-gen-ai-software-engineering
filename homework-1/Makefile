.PHONY: up down restart logs shell composer test phpunit setup

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

phpunit:
	docker compose exec php ./vendor/bin/phpunit

setup: up
	docker compose exec php composer install
	docker compose exec php php bin/setup.php
	docker compose exec php chown -R www-data:www-data /var/www/data
	docker compose exec php chmod 755 /var/www/data
	docker compose exec php chmod 644 /var/www/data/database.sqlite
