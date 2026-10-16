# Everything runs inside Docker. These are shortcuts for the commands used most.

HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
export HOST_UID HOST_GID

COMPOSE := docker compose
RUN := $(COMPOSE) exec app

.DEFAULT_GOAL := help

.PHONY: help setup up down restart build logs shell artisan composer npm migrate fresh test lint

help: ## List the available commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-10s %s\n", $$1, $$2}'

setup: ## First run on a new machine: build, install, migrate
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps app composer install
	$(COMPOSE) run --rm --no-deps app npm install
	$(COMPOSE) up -d
	@grep -q '^APP_KEY=base64' .env || $(RUN) php artisan key:generate
	$(RUN) php artisan migrate --no-interaction
	@echo ""
	@echo "Halfpast is running at http://localhost:$${APP_PORT:-8000}"
	@echo "Emails are caught at   http://localhost:$${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}"

up: ## Start every container
	$(COMPOSE) up -d

down: ## Stop every container
	$(COMPOSE) down

restart: down up ## Stop, then start again

build: ## Rebuild the app image
	$(COMPOSE) build

logs: ## Follow the logs of every container
	$(COMPOSE) logs -f

shell: ## Open a shell inside the app container
	$(RUN) bash

artisan: ## Run an artisan command, e.g. make artisan c="route:list"
	$(RUN) php artisan $(c)

composer: ## Run a composer command, e.g. make composer c="require foo/bar"
	$(RUN) composer $(c)

npm: ## Run an npm command, e.g. make npm c="run build"
	$(RUN) npm $(c)

migrate: ## Run new migrations
	$(RUN) php artisan migrate

fresh: ## Rebuild the database from scratch and seed it
	$(RUN) php artisan migrate:fresh --seed

test: ## Run the test suite
	$(RUN) php artisan test

lint: ## Check code style and static analysis
	$(RUN) vendor/bin/pint --test
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G
