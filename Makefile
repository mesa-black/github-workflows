include .env
export

DOCKER 			   	= docker
DOCKER_COMPOSE 	   	= docker compose
EXEC 			   	= $(DOCKER_COMPOSE) run --rm --interactive --tty
APP				   	= $(EXEC) php
COMPOSER		   	= $(APP) composer
CONSOLE			   	= $(APP) bin/console
QA                 	= $(EXEC) -e PHP_VERSION=8.3 qa
ANSI_COLOR		   	= --ansi

# Colors
GREEN  := $(shell tput -Txterm setaf 2)
RED    := $(shell tput -Txterm setaf 1)
YELLOW := $(shell tput -Txterm setaf 3)
BLUE   := $(shell tput -Txterm setaf 4)
RESET  := $(shell tput -Txterm sgr0)

.DEFAULT_GOAL := help

##
## —— 🛠️ Others ——
.PHONY: help
help: ## List of commands
	@grep -E '(^[a-z0-9A-Z_-]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## —— 🔥 Project ——
.PHONY: install
install: ## Project Installation
install: start vendor
	@echo "${GREEN}You can use make workflow-delete to delete all workflow post 15 days"

.PHONY: start
start: ## Start the containers
	$(DOCKER_COMPOSE) up --detach --remove-orphans

.PHONY: stop
stop: ## Stop the containers
	$(DOCKER_COMPOSE) stop

.PHONY: restart
restart: ## restart the containers
restart: stop start

.PHONY: kill
kill: ## Forces running containers to stop by sending a SIGKILL signal
	$(DOCKER_COMPOSE) kill

.PHONY: down
down: ## Stops containers
	$(DOCKER_COMPOSE) down --volumes --remove-orphans

.PHONY: reset
reset: ## Stop and start a fresh install of the project
reset: kill down install

.PHONY: vendor
vendor: ## Install composer dependencies
vendor:
	$(COMPOSER) install --no-progress --no-suggest --prefer-dist --optimize-autoloader

.PHONY: workflow-delete
workflow-delete: ## Delete all workflows post 15 days
workflow-delete:
	$(CONSOLE) app:workflow:delete

##
## —— ✨ Code Quality ——
.PHONY: qa
qa: ## Run all code quality checks
qa: lint-yaml lint-container phpdd phpcs php-cs-fixer phpstan security-check

.PHONY: qa-fix
qa-fix: ## Run all code quality fixers
qa-fix: php-cs-fixer-apply

.PHONY: lint-yaml
lint-yaml: ## Lints YAML files
lint-yaml:
	$(PHP) vendor/bin/yaml-lint config --parse-tags $(ANSI_COLOR)

.PHONY: lint-containers
lint-container: ## Lints containers
lint-container:
	$(CONSOLE) cache:clear --env=prod $(ANSI_COLOR)
	$(CONSOLE) lint:container $(ANSI_COLOR)

.PHONY: phpcs
phpcs: ## PHP_CodeSniffer (https://github.com/squizlabs/PHP_CodeSniffer)
	$(QA) phpcs -p -n --colors --standard=.phpcs.xml src --colors

.PHONY: phpmnd
phpmnd: ## Detect magic numbers in your PHP code
	$(QA) phpmnd src tests $(ANSI_COLOR)

.PHONY: phpdd
phpdd: ## Detect deprecations
	$(QA) phpdd src tests $(ANSI_COLOR)

.PHONY: phpstan
phpstan: ## PHP Static Analysis Tool (https://github.com/phpstan/phpstan)
	$(QA) phpstan --memory-limit=-1 analyse $(ANSI_COLOR)

.PHONY: php-cs-fixer
php-cs-fixer: ## PhpCsFixer (https://cs.symfony.com/)
	$(QA) php-cs-fixer fix --using-cache=no --verbose --diff --dry-run $(ANSI_COLOR)

.PHONY: php-cs-fixer-apply
php-cs-fixer-apply: ## Applies PhpCsFixer fixes
	$(QA) php-cs-fixer fix --using-cache=no --verbose --diff $(ANSI_COLOR)

.PHONY: security-check
security-check: ## SensioLabs Security Checker
	-$(APP) composer audit $(ANSI_COLOR)
