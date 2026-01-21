.PHONY: help build start stop restart clean setup install migrate fixtures test logs shell db-shell frontend-shell backend-shell production-build

# Colors for output
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[0;33m
BLUE := \033[0;34m
NC := \033[0m # No Color

# Check for .env.dev file and set docker-compose command accordingly
ifeq ($(wildcard .env.dev),)
    DOCKER_COMPOSE := docker-compose
    ENV_FILE_MSG := '${BLUE}Using .env file${NC}'
else
    DOCKER_COMPOSE := docker-compose --env-file .env.dev
    ENV_FILE_MSG := '${GREEN}Using .env.dev file${NC}'
endif

help: ## Show this help message
	@echo '${BLUE}Vehicle Management System - Makefile${NC}'
	@echo ''
	@echo '${GREEN}Usage:${NC}'
	@echo '  make ${YELLOW}<target>${NC}'
	@echo ''
	@echo '${GREEN}Available targets:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  ${YELLOW}%-20s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build all Docker containers
	@echo '${BLUE}Building Docker containers...${NC}'
	@echo $(ENV_FILE_MSG)
	$(DOCKER_COMPOSE) build

start: ## Start all services
	@echo '${BLUE}Starting services...${NC}'
	@echo $(ENV_FILE_MSG)
	$(DOCKER_COMPOSE) up -d
	@echo '${GREEN}Services started successfully!${NC}'
	@echo '${YELLOW}Frontend:${NC} http://localhost:3000'
	@echo '${YELLOW}Backend API:${NC} http://localhost:8000'

stop: ## Stop all services
	@echo '${BLUE}Stopping services...${NC}'
	$(DOCKER_COMPOSE) stop
	@echo '${GREEN}Services stopped.${NC}'

restart: stop start ## Restart all services

down: ## Stop and remove all containers
	@echo '${RED}Stopping and removing containers...${NC}'
	$(DOCKER_COMPOSE) down
	@echo '${GREEN}Containers removed.${NC}'

clean: ## Remove containers, volumes, and generated files
	@echo '${RED}Cleaning up...${NC}'
	$(DOCKER_COMPOSE) down -v
	rm -rf backend/var/cache/*
	rm -rf backend/var/log/*
	rm -rf frontend/node_modules
	rm -rf frontend/build
	@echo '${GREEN}Cleanup complete.${NC}'

setup: build ## Initial setup - build, install dependencies, setup database
	@echo '${BLUE}Running initial setup...${NC}'
	$(MAKE) start
	@echo '${YELLOW}Waiting for services to be ready...${NC}'
	sleep 10
	$(MAKE) install
	$(MAKE) jwt-keys
	$(MAKE) migrate
	$(MAKE) fixtures
	@echo '${GREEN}Setup complete!${NC}'
	@echo ''
	@echo '${YELLOW}Default admin credentials:${NC}'
	@echo '  Email: admin@vehicle.local'
	@echo '  Password: changeme'
	@echo '  ${RED}(You will be prompted to change this password on first login)${NC}'

install: ## Install backend and frontend dependencies
	@echo '${BLUE}Installing backend dependencies...${NC}'
	$(DOCKER_COMPOSE) exec php composer install
	@echo '${BLUE}Installing frontend dependencies...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm install

jwt-keys: ## Generate JWT keys
	@echo '${BLUE}Generating JWT keys...${NC}'
	$(DOCKER_COMPOSE) exec php sh -c "mkdir -p config/jwt && \
		openssl genrsa -out config/jwt/private.pem 4096 && \
		openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
		chmod 644 config/jwt/private.pem config/jwt/public.pem"
	@echo '${GREEN}JWT keys generated.${NC}'

migrate: ## Run database migrations
	@echo '${BLUE}Running database migrations...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction
	@echo '${GREEN}Migrations complete.${NC}'

fixtures: ## Load database fixtures
	@echo '${BLUE}Loading database fixtures...${NC}'
	@if [ -z "$(FORCE_FIXTURES)" ] && [ "$(APP_ENV)" != "test" ]; then \
		echo '${RED}Refusing to load fixtures into non-test environment. Set FORCE_FIXTURES=1 or APP_ENV=test to proceed.${NC}'; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) exec php bin/console doctrine:fixtures:load --no-interaction
	@echo '${GREEN}Fixtures loaded.${NC}'
	@echo '${YELLOW}Admin user created:${NC} admin@vehicle.local / changeme'

db-create: ## Create database
	@echo '${BLUE}Creating database...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console doctrine:database:create --if-not-exists
	@echo '${GREEN}Database created.${NC}'

db-drop: ## Drop database (WARNING: destroys all data)
	@echo '${RED}Dropping database...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console doctrine:database:drop --force --if-exists
	@echo '${GREEN}Database dropped.${NC}'

db-reset: db-drop db-create migrate fixtures ## Reset database completely

cache-clear: ## Clear Symfony cache
	@echo '${BLUE}Clearing cache...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console cache:clear
	@echo '${GREEN}Cache cleared.${NC}'

test: ## Run tests
	@echo '${BLUE}Running tests...${NC}'
	$(DOCKER_COMPOSE) exec php bin/phpunit
	@echo '${GREEN}Tests complete.${NC}'

logs: ## Show logs for all services
	$(DOCKER_COMPOSE) logs -f

logs-backend: ## Show backend logs
	$(DOCKER_COMPOSE) logs -f php nginx

logs-frontend: ## Show frontend logs
	$(DOCKER_COMPOSE) logs -f frontend

logs-db: ## Show database logs
	$(DOCKER_COMPOSE) logs -f mysql

shell: backend-shell ## Open shell in backend container (alias)

backend-shell: ## Open bash shell in backend container
	@echo '${BLUE}Opening backend shell...${NC}'
	$(DOCKER_COMPOSE) exec php bash

frontend-shell: ## Open shell in frontend container
	@echo '${BLUE}Opening frontend shell...${NC}'
	$(DOCKER_COMPOSE) exec frontend sh

db-shell: ## Open MySQL shell
	@echo '${BLUE}Opening database shell...${NC}'
	$(DOCKER_COMPOSE) exec mysql mysql -u vehicle -pvehicle vehicle

ps: ## Show running containers
	$(DOCKER_COMPOSE) ps

frontend-dev: ## Start frontend in development mode
	@echo '${BLUE}Starting frontend in development mode...${NC}'
	$(DOCKER_COMPOSE) up frontend

frontend-build: ## Build frontend for production
	@echo '${BLUE}Building frontend for production...${NC}'
	$(DOCKER_COMPOSE) run --rm frontend npm run build
	@echo '${GREEN}Frontend build complete.${NC}'

composer-update: ## Update Composer dependencies
	@echo '${BLUE}Updating Composer dependencies...${NC}'
	$(DOCKER_COMPOSE) exec php composer update
	@echo '${GREEN}Composer dependencies updated.${NC}'

npm-update: ## Update npm dependencies
	@echo '${BLUE}Updating npm dependencies...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm update
	@echo '${GREEN}npm dependencies updated.${NC}'

production-build: ## Build for production
	@echo '${BLUE}Building for production...${NC}'
	$(MAKE) build
	$(MAKE) install
	$(MAKE) frontend-build
	$(MAKE) cache-clear
	@echo '${GREEN}Production build complete.${NC}'

lint-backend: ## Lint backend code
	@echo '${BLUE}Linting backend code...${NC}'
	$(DOCKER_COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff

lint-frontend: ## Lint frontend code
	@echo '${BLUE}Linting frontend code...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm run lint

format-backend: ## Format backend code
	@echo '${BLUE}Formatting backend code...${NC}'
	$(DOCKER_COMPOSE) exec php vendor/bin/php-cs-fixer fix

format-frontend: ## Format frontend code
	@echo '${BLUE}Formatting frontend code...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm run format

status: ## Show application status
	@echo '${BLUE}Application Status:${NC}'
	@echo ''
	@$(DOCKER_COMPOSE) ps
	@echo ''
	@echo '${YELLOW}Endpoints:${NC}'
	@echo '  Frontend: http://localhost:3000'
	@echo '  Backend API: http://localhost:8000'
	@echo '  MySQL: localhost:3306'

backup-db: ## Backup database
	@echo '${BLUE}Backing up database...${NC}'
	@mkdir -p backups
	$(DOCKER_COMPOSE) exec -T mysql mysqldump -u vehicle -pvehicle vehicle > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo '${GREEN}Database backup created.${NC}'

restore-db: ## Restore database from backup (usage: make restore-db FILE=backups/backup.sql)
	@if [ -z "$(FILE)" ]; then \
		echo '${RED}Error: Please specify backup file with FILE=backups/backup.sql${NC}'; \
		exit 1; \
	fi
	@echo '${BLUE}Restoring database from $(FILE)...${NC}'
	$(DOCKER_COMPOSE) exec -T mysql mysql -u vehicle -pvehicle vehicle < $(FILE)
	@echo '${GREEN}Database restored.${NC}'

# Default target
.DEFAULT_GOAL := help
