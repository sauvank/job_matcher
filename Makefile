.DEFAULT_GOAL := help

.PHONY: help setup build up down logs shell worker test phpstan cs-check qa ci

help:
	@echo "make setup     Build and start the application"
	@echo "make up        Start all containers"
	@echo "make down      Stop all containers"
	@echo "make logs      Follow container logs"
	@echo "make shell     Open a shell in the PHP container"
	@echo "make test      Run PHPUnit"
	@echo "make qa        Run coding style, PHPStan and tests"

setup: build up

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

shell:
	docker compose exec php sh

worker:
	docker compose up -d worker

test:
	docker compose run --rm -e APP_ENV=test php composer test

phpstan:
	docker compose run --rm php composer phpstan

cs-check:
	docker compose run --rm php composer cs-check

qa:
	docker compose run --rm -e APP_ENV=test php composer qa

ci: qa
