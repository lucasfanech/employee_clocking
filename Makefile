.DEFAULT_GOAL := help
COMPOSE ?= docker compose
PHP     := $(COMPOSE) exec php
CONSOLE := $(PHP) php bin/console

.PHONY: help up down build logs sh console migrate migration user test lint assets

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Start the development stack (app on :8000, phpMyAdmin on :8080)
	$(COMPOSE) up -d --build

down: ## Stop the stack
	$(COMPOSE) down

build: ## Rebuild the images
	$(COMPOSE) build

logs: ## Tail the logs
	$(COMPOSE) logs -f --tail=100

sh: ## Shell inside the php container
	$(PHP) bash

console: ## Run a Symfony console command: make console c="debug:router"
	$(CONSOLE) $(c)

migrate: ## Run the database migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

migration: ## Generate a migration from the entity changes
	$(CONSOLE) make:migration

user: ## Create an account: make user email=you@example.com [admin=1]
	$(CONSOLE) app:user:create $(email) $(if $(admin),--admin,)

test: ## Run the test suite (SQLite)
	$(PHP) php bin/phpunit

lint: ## Lint container, Twig templates and YAML files
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config

assets: ## Build the production assets
	$(COMPOSE) run --rm node npm run build
