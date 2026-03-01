SHELL := /bin/sh

DC := docker compose -f compose.yaml
DC_EXEC := $(DC) exec -T app
DC_EXEC_TEST := $(DC) exec -T -e APP_ENV=test -e APP_DEBUG=1 app

.PHONY: help
help: ## Show help
	@echo "For global application, use Makefile at the root project."
	@echo "Please use 'make <target>' where <target> is one of"
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | \
		sed -e 's/\[32m##/[33m/'

##
## Container
##---------------------------------------------------------------------------

.PHONY: up
up: ## Start stack
	$(DC) up -d

.PHONY: up-rebuild
up-rebuild: ## Start stack (rebuild + force recreate)
	$(DC) down --remove-orphans
	$(DC) up -d --build --force-recreate

.PHONY: down
down: ## Stop all containers
	$(DC) down --remove-orphans

.PHONY: build
build: ## Build images
	$(DC) build

.PHONY: rebuild
rebuild: ## Rebuild images (no cache)
	$(DC) build --no-cache

.PHONY: logs
logs: ## Follow logs
	$(DC) logs -f

.PHONY: ps
ps: ## List containers
	$(DC) ps

.PHONY: restart-app
restart-app: ## Restart app container only
	$(DC) restart app

.PHONY: shell
shell: ## Open a shell in app container
	$(DC_EXEC) sh

##
## Composer
##---------------------------------------------------------------------------

.PHONY: composer-install
composer-install: ## Install composer dependencies
	$(DC_EXEC) composer install --no-progress

##
## Symfony
##---------------------------------------------------------------------------

.PHONY: cache-clear
cache-clear: ## Clear Symfony cache (dev)
	$(DC_EXEC) php bin/console cache:clear

.PHONY: audit-retention-dry-run
audit-retention-dry-run: ## Dry-run purge for auth/admin audit logs
	$(DC_EXEC) php bin/console app:audit:retention:run --days=90 --dry-run

.PHONY: audit-retention-run
audit-retention-run: ## Purge auth/admin audit logs
	$(DC_EXEC) php bin/console app:audit:retention:run --days=90

.PHONY: minerva-refresh-dry-run
minerva-refresh-dry-run: ## Dry-run Minerva rotation refresh (next 90 days)
	$(DC_EXEC) php bin/console app:minerva:refresh-rotation --days=90 --dry-run

.PHONY: minerva-refresh-check
minerva-refresh-check: ## Dry-run Minerva refresh with non-zero exit when coverage has gaps
	$(DC_EXEC) php bin/console app:minerva:refresh-rotation --days=90 --dry-run --fail-on-missing

.PHONY: minerva-refresh-run
minerva-refresh-run: ## Minerva rotation refresh (next 90 days)
	$(DC_EXEC) php bin/console app:minerva:refresh-rotation --days=90

##
## Database
##---------------------------------------------------------------------------

.PHONY: db-init
db-init: ## Drop schema, create schema, run migrations
	$(DC_EXEC) php bin/console doctrine:schema:drop --force --full-database
	$(DC_EXEC) php bin/console doctrine:database:create --if-not-exists
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: db-migrate
db-migrate: ## Run migrations (no interaction)
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: db-diff
db-diff: ## Generate migration (only if DB is up to date)
	$(DC_EXEC) php bin/console doctrine:migrations:up-to-date
	$(DC_EXEC) php bin/console doctrine:migrations:diff

.PHONY: db-test-init
db-test-init: ## Create/migrate test database
	-$(DC_EXEC) php bin/console doctrine:database:drop --env=test --if-exists --force --no-interaction
	$(DC_EXEC) php bin/console doctrine:database:create --env=test --if-not-exists
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --env=test --no-interaction

##
## Tests
##---------------------------------------------------------------------------

.PHONY: phpunit-all
phpunit-all: ## Run all PHPUnit tests
	$(DC_EXEC_TEST) vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit,Functional,Integration

.PHONY: phpunit-unit
phpunit-unit: ## Run PHPUnit Unit suite
	$(DC_EXEC_TEST) vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit

.PHONY: phpunit-integration
phpunit-integration: db-test-init ## Run PHPUnit Integration suite
	$(DC_EXEC_TEST) vendor/bin/phpunit --configuration phpunit.xml --testsuite Integration

.PHONY: phpunit-functional
phpunit-functional: db-test-init ## Run PHPUnit Functional suite
	$(DC_EXEC_TEST) vendor/bin/phpunit --configuration phpunit.xml --testsuite Functional


##
## Quality
##---------------------------------------------------------------------------

.PHONY: phpstan
phpstan: ## Run PHPStan
	$(DC_EXEC) vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit 2G

.PHONY: phpstan-baseline
phpstan-baseline: ## Generate PHPStan baseline
	$(DC_EXEC) vendor/bin/phpstan analyse -c phpstan.dist.neon --generate-baseline --memory-limit 2G

.PHONY: php-cs-fixer
php-cs-fixer: ## Run PHP-CS-Fixer (apply fixes)
	$(DC_EXEC) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

.PHONY: php-cs-fixer-check
php-cs-fixer-check: ## Run PHP-CS-Fixer (dry-run)
	$(DC_EXEC) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff
