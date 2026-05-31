.PHONY: up down build shell test qa cs stan

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec php sh

test:
	docker compose exec php vendor/bin/phpunit --testdox

stan:
	docker compose exec php vendor/bin/phpstan analyse src tests --level=8

cs:
	docker compose exec php vendor/bin/php-cs-fixer fix --diff

qa: stan cs test
