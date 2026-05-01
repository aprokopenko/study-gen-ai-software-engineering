.PHONY: setup up down restart logs shell composer phpunit test

DC_PHP = docker compose exec php

setup:
	docker compose build
	docker compose up -d
	$(DC_PHP) composer install
	$(DC_PHP) php bin/setup.php
	$(DC_PHP) chown -R www-data:www-data /var/www/data
	$(DC_PHP) chmod 755 /var/www/data
	$(DC_PHP) chmod 644 /var/www/data/*.sqlite
up:
	docker compose up --build -d

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f

shell:
	docker compose exec php bash

composer:
	$(DC_PHP) composer $(args)

phpunit:
	$(DC_PHP) ./vendor/bin/phpunit

test:
	curl -s http://localhost:3000/
	
chown:
	sudo chown -R $$(whoami):$$(whoami) src/ data/