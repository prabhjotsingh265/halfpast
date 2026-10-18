# Everything runs inside Docker. These are shortcuts for the commands used most.

HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
export HOST_UID HOST_GID

COMPOSE := docker compose
RUN := $(COMPOSE) exec app

.DEFAULT_GOAL := help

.PHONY: help setup up down restart build logs shell artisan composer npm migrate fresh test lint stripe-webhooks

help: ## List the available commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-10s %s\n", $$1, $$2}'

setup: ## First run on a new machine: build, install, migrate, add the demo salon
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps app composer install
	$(COMPOSE) run --rm --no-deps app npm install
	$(COMPOSE) up -d
	@grep -q '^APP_KEY=base64' .env || $(RUN) php artisan key:generate
	@test -L public/storage || $(RUN) php artisan storage:link
	$(RUN) php artisan migrate --seed --no-interaction
	@echo ""
	@echo "Halfpast is running at http://localhost:$${APP_PORT:-8000}"
	@echo "Demo login:            demo@halfpast.test / password"
	@echo "Demo booking page:     http://localhost:$${APP_PORT:-8000}/kesar-beauty-lounge"
	@echo "Emails are caught at   http://localhost:$${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}"

up: ## Start every container (and Stripe's webhook forwarder when Stripe keys are in .env)
	$(COMPOSE) up -d
	@if grep -qE '^STRIPE_SECRET=.+' .env 2>/dev/null; then $(MAKE) --no-print-directory stripe-webhooks; fi

down: ## Stop every container
	$(COMPOSE) --profile stripe down

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

stripe-webhooks: ## Forward Stripe test webhooks to the app (run by make up when Stripe keys are set)
	@secret=$$($(COMPOSE) --profile stripe run --rm --no-deps stripe listen --print-secret 2>/dev/null | tr -d '\r' | tail -1); \
	case "$$secret" in whsec_*) ;; *) echo "Couldn't get a webhook secret from Stripe. Is STRIPE_SECRET set in .env?"; exit 1;; esac; \
	if grep -q "^STRIPE_WEBHOOK_SECRET=$$secret$$" .env; then exit 0; fi; \
	if grep -q '^STRIPE_WEBHOOK_SECRET=' .env; then \
		sed -i.bak "s|^STRIPE_WEBHOOK_SECRET=.*|STRIPE_WEBHOOK_SECRET=$$secret|" .env && rm -f .env.bak; \
	else echo "STRIPE_WEBHOOK_SECRET=$$secret" >> .env; fi; \
	echo "Saved a new Stripe webhook signing secret in .env."; \
	$(RUN) php artisan config:clear >/dev/null; \
	$(COMPOSE) restart queue scheduler
	@$(COMPOSE) --profile stripe up -d stripe
	@echo "Stripe test webhooks reach http://localhost:$${APP_PORT:-8000}/webhooks/stripe"
