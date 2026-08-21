.DEFAULT_GOAL := help

.PHONY: help setup build up down restart logs shell worker install sass migrate migration-status migration-diff schema-validate fixtures test-db test phpstan cs-check qa ci

help:
	@echo "make setup             Install and start the application"
	@echo "make up                Start all containers"
	@echo "make down              Stop all containers"
	@echo "make restart           Restart application containers"
	@echo "make logs              Follow container logs"
	@echo "make shell             Open a shell in the PHP container"
	@echo "make install           Install Composer dependencies"
	@echo "make sass              Compile SCSS assets"
	@echo "make migrate           Apply pending database migrations"
	@echo "make migration-status  Show database migration status"
	@echo "make migration-diff    Generate a migration from entity changes"
	@echo "make schema-validate   Validate Doctrine mapping and schema"
	@echo "make fixtures          Reload development fixtures"
	@echo "make test-db           Prepare and migrate the test database"
	@echo "make test              Run PHPUnit"
	@echo "make qa                Run coding style, PHPStan and tests"

setup: build up install migrate sass

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose up -d --force-recreate php worker nginx

logs:
	docker compose logs -f

shell:
	docker compose exec php sh

worker:
	docker compose up -d worker

install:
	docker compose exec -T php composer install --prefer-dist --no-interaction --no-progress

sass:
	docker compose exec -T php php bin/console sass:build

migrate:
	docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

migration-status:
	docker compose exec -T php php bin/console doctrine:migrations:status

migration-diff:
	docker compose exec -T php php bin/console doctrine:migrations:diff

schema-validate:
	docker compose exec -T php php bin/console doctrine:schema:validate

fixtures:
	docker compose exec -T php php bin/console doctrine:fixtures:load --no-interaction

test-db:
	docker compose run --rm -e APP_ENV=test php php bin/console doctrine:database:create --if-not-exists
	docker compose run --rm -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction

test: test-db
	docker compose run --rm -e APP_ENV=test php composer test

phpstan:
	docker compose run --rm php composer phpstan

cs-check:
	docker compose run --rm php composer cs-check

qa: test-db
	docker compose run --rm -e APP_ENV=test php composer qa

ci: qa
