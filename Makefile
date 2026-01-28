.PHONY: help build build-dev build-prod build-no-cache start start-dev start-prod stop restart down clean setup install migrate fixtures test logs shell db-shell frontend-shell backend-shell nginx-shell

# Colors for output
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[0;33m
BLUE := \033[0;34m
MAGENTA := \033[0;35m
CYAN := \033[0;36m
NC := \033[0m # No Color

# Docker Compose configurations
COMPOSE_DEV := docker-compose
COMPOSE_PROD := docker-compose -f docker-compose.yml -f docker-compose.prod.yml
COMPOSE_OVERRIDE := docker-compose -f docker-compose.yml -f docker-compose.override.yml

# Check for .env.dev file and set docker-compose command accordingly
ifeq ($(wildcard .env.dev),)
    DOCKER_COMPOSE := $(COMPOSE_DEV)
    ENV_FILE_MSG := '${BLUE}Using .env file${NC}'
else
    DOCKER_COMPOSE := docker-compose --env-file .env.dev
    ENV_FILE_MSG := '${GREEN}Using .env.dev file${NC}'
endif

help: ## Show this help message
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'
	@echo '${CYAN}   Vehicle Management System - Makefile Commands${NC}'
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'
	@echo ''
	@echo '${GREEN}Usage:${NC}'
	@echo '  make ${YELLOW}<target>${NC}'
	@echo ''
	@echo '${GREEN}🏗️  Build Commands:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^build.*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}🚀 Service Commands:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^(start|stop|restart|down|up).*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}🔧 Development Commands:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^(install|migrate|fixtures|jwt|cache|lint|format).*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}🗄️  Database Commands:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^db-.*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}🧪 Testing & Logs:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^(test|logs).*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}🐚 Shell Access:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^.*-shell.*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${GREEN}📦 Utility Commands:${NC}'
	@awk 'BEGIN {FS = ":.*?## "} /^(status|ps|clean|backup|restore|setup).*:.*?## / {printf "  ${YELLOW}%-25s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ''
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'

## Build Commands

build: build-dev ## Build all containers (development mode, default)

build-dev: ## Build all containers for development
	@echo '${BLUE}Building development containers...${NC}'
	@echo $(ENV_FILE_MSG)
	$(DOCKER_COMPOSE) build
	@echo '${GREEN}✓ Development build complete${NC}'

build-prod: ## Build all containers for production
	@echo '${MAGENTA}Building production containers...${NC}'
	$(COMPOSE_PROD) build
	@echo '${GREEN}✓ Production build complete${NC}'

build-no-cache: ## Build all containers without cache
	@echo '${BLUE}Building containers without cache...${NC}'
	@echo $(ENV_FILE_MSG)
	$(DOCKER_COMPOSE) build --no-cache
	@echo '${GREEN}✓ Build complete${NC}'

build-frontend-dev: ## Build frontend for development
	@echo '${BLUE}Building frontend (development stage)...${NC}'
	$(DOCKER_COMPOSE) build --build-arg BUILDKIT_INLINE_CACHE=1 frontend
	@echo '${GREEN}✓ Frontend development build complete${NC}'

build-frontend-prod: ## Build frontend for production (nginx-served)
	@echo '${MAGENTA}Building frontend (production stage)...${NC}'
	$(COMPOSE_PROD) build frontend
	@echo '${GREEN}✓ Frontend production build complete${NC}'

build-backend: ## Build backend (PHP-FPM)
	@echo '${BLUE}Building backend...${NC}'
	$(DOCKER_COMPOSE) build php
	@echo '${GREEN}✓ Backend build complete${NC}'

build-nginx: ## Build nginx container
	@echo '${BLUE}Building nginx...${NC}'
	$(DOCKER_COMPOSE) build nginx
	@echo '${GREEN}✓ Nginx build complete${NC}'

## Service Commands

start: start-dev ## Start all services (development mode, default)

start-dev: ## Start development services
	@echo '${BLUE}Starting development services...${NC}'
	@echo $(ENV_FILE_MSG)
	$(DOCKER_COMPOSE) up -d
	@echo '${GREEN}✓ Development services started${NC}'
	@echo ''
	@echo '${CYAN}Available endpoints:${NC}'
	@echo '  ${YELLOW}Frontend (dev):${NC}  http://localhost:3000'
	@echo '  ${YELLOW}Backend API:${NC}     http://localhost:8081'
	@echo '  ${YELLOW}MySQL:${NC}           localhost:3306'

start-prod: ## Start production services
	@echo '${MAGENTA}Starting production services...${NC}'
	$(COMPOSE_PROD) up -d
	@echo '${GREEN}✓ Production services started${NC}'
	@echo ''
	@echo '${CYAN}Available endpoints:${NC}'
	@echo '  ${YELLOW}Frontend (prod):${NC} http://localhost:80'
	@echo '  ${YELLOW}Backend API:${NC}     http://localhost:8081'
	@echo '  ${YELLOW}MySQL:${NC}           localhost:3306'

up-dev: start-dev ## Alias for start-dev

up-prod: start-prod ## Alias for start-prod

stop: ## Stop all services
	@echo '${BLUE}Stopping services...${NC}'
	$(DOCKER_COMPOSE) stop
	@echo '${GREEN}✓ Services stopped${NC}'

stop-prod: ## Stop production services
	@echo '${MAGENTA}Stopping production services...${NC}'
	$(COMPOSE_PROD) stop
	@echo '${GREEN}✓ Production services stopped${NC}'

restart: stop start ## Restart all services (development)

restart-dev: stop start-dev ## Restart development services

restart-prod: stop-prod start-prod ## Restart production services

down: ## Stop and remove containers (development)
	@echo '${RED}Stopping and removing containers...${NC}'
	$(DOCKER_COMPOSE) down
	@echo '${GREEN}✓ Containers removed${NC}'

down-prod: ## Stop and remove production containers
	@echo '${RED}Stopping and removing production containers...${NC}'
	$(COMPOSE_PROD) down
	@echo '${GREEN}✓ Production containers removed${NC}'

## Utility Commands

clean: ## Remove containers, volumes, and build artifacts
	@echo '${RED}Cleaning up...${NC}'
	$(DOCKER_COMPOSE) down -v
	rm -rf backend/var/cache/*
	rm -rf backend/var/log/*
	rm -rf frontend/node_modules
	rm -rf frontend/build
	@echo '${GREEN}✓ Cleanup complete${NC}'

clean-all: ## Deep clean including Docker images
	@echo '${RED}Deep cleaning (including images)...${NC}'
	$(DOCKER_COMPOSE) down -v --rmi all
	$(COMPOSE_PROD) down -v --rmi all 2>/dev/null || true
	rm -rf backend/var/cache/*
	rm -rf backend/var/log/*
	rm -rf frontend/node_modules
	rm -rf frontend/build
	docker system prune -f
	@echo '${GREEN}✓ Deep cleanup complete${NC}'

prune: ## Remove all unused Docker resources
	@echo '${RED}Pruning Docker resources...${NC}'
	docker system prune -af --volumes
	@echo '${GREEN}✓ Docker resources pruned${NC}'

## Setup and Installation

setup: build ## Initial setup - build, install, configure (development)
	@echo '${BLUE}Running initial development setup...${NC}'
	$(MAKE) start
	@echo '${YELLOW}Waiting for services to be ready...${NC}'
	sleep 10
	$(MAKE) install
	$(MAKE) jwt-keys
	$(MAKE) migrate
	$(MAKE) fixtures
	@echo '${GREEN}✓ Setup complete!${NC}'
	@echo ''
	@echo '${YELLOW}Default admin credentials:${NC}'
	@echo '  Email: admin@vehicle.local'
	@echo '  Password: changeme'
	@echo '  ${RED}(You will be prompted to change this password on first login)${NC}'

setup-prod: build-prod ## Initial production setup
	@echo '${MAGENTA}Running initial production setup...${NC}'
	$(MAKE) start-prod
	@echo '${YELLOW}Waiting for services to be ready...${NC}'
	sleep 15
	$(MAKE) install-prod
	$(MAKE) jwt-keys-prod
	$(MAKE) migrate-prod
	@echo '${GREEN}✓ Production setup complete!${NC}'
	@echo ''
	@echo '${RED}⚠ WARNING: Create admin user manually in production${NC}'

install: ## Install dependencies (development)
	@echo '${BLUE}Installing dependencies...${NC}'
	@echo '${CYAN}→ Backend dependencies...${NC}'
	$(DOCKER_COMPOSE) exec php composer install
	@echo '${CYAN}→ Frontend dependencies...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm install
	@echo '${GREEN}✓ Dependencies installed${NC}'

install-prod: ## Install dependencies (production)
	@echo '${MAGENTA}Installing production dependencies...${NC}'
	@echo '${CYAN}→ Backend dependencies...${NC}'
	$(COMPOSE_PROD) exec php composer install --no-dev --optimize-autoloader
	@echo '${GREEN}✓ Production dependencies installed${NC}'

jwt-keys: ## Generate JWT keys (development)
	@echo '${BLUE}Generating JWT keys...${NC}'
	$(DOCKER_COMPOSE) exec php sh -c "mkdir -p config/jwt && \
		openssl genrsa -out config/jwt/private.pem 4096 && \
		openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
		chmod 644 config/jwt/private.pem config/jwt/public.pem"
	@echo '${GREEN}✓ JWT keys generated${NC}'

jwt-keys-prod: ## Generate JWT keys (production)
	@echo '${MAGENTA}Generating JWT keys for production...${NC}'
	$(COMPOSE_PROD) exec php sh -c "mkdir -p config/jwt && \
		openssl genrsa -out config/jwt/private.pem 4096 && \
		openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
		chmod 600 config/jwt/private.pem && chmod 644 config/jwt/public.pem"
	@echo '${GREEN}✓ JWT keys generated${NC}'

## Database Commands

migrate: ## Run database migrations (development)
	@echo '${BLUE}Running database migrations...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction
	@echo '${GREEN}✓ Migrations complete${NC}'

migrate-prod: ## Run database migrations (production)
	@echo '${MAGENTA}Running database migrations (production)...${NC}'
	$(COMPOSE_PROD) exec php bin/console doctrine:migrations:migrate --no-interaction
	@echo '${GREEN}✓ Migrations complete${NC}'

fixtures: ## Load database fixtures (development only)
	@echo '${BLUE}Loading database fixtures...${NC}'
	@if [ -z "$(FORCE_FIXTURES)" ] && [ "$(APP_ENV)" != "test" ] && [ "$(APP_ENV)" != "dev" ]; then \
		echo '${RED}⚠ Refusing to load fixtures. Set FORCE_FIXTURES=1 or APP_ENV=test/dev${NC}'; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) exec php bin/console doctrine:fixtures:load --no-interaction
	@echo '${GREEN}✓ Fixtures loaded${NC}'
	@echo '${YELLOW}Admin user:${NC} admin@vehicle.local / changeme'

db-create: ## Create database
	@echo '${BLUE}Creating database...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console doctrine:database:create --if-not-exists
	@echo '${GREEN}✓ Database created${NC}'

db-drop: ## Drop database (WARNING: destroys all data)
	@echo '${RED}⚠ WARNING: Dropping database...${NC}'
	@echo '${YELLOW}This will destroy ALL data. Press Ctrl+C to cancel, or wait 5 seconds...${NC}'
	@sleep 5
	$(DOCKER_COMPOSE) exec php bin/console doctrine:database:drop --force --if-exists
	@echo '${GREEN}✓ Database dropped${NC}'

db-reset: db-drop db-create migrate fixtures ## Reset database completely

## Caching

cache-clear: ## Clear Symfony cache (development)
	@echo '${BLUE}Clearing cache...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console cache:clear
	@echo '${GREEN}✓ Cache cleared${NC}'

cache-clear-prod: ## Clear Symfony cache (production)
	@echo '${MAGENTA}Clearing production cache...${NC}'
	$(COMPOSE_PROD) exec php bin/console cache:clear --env=prod --no-debug
	@echo '${GREEN}✓ Production cache cleared${NC}'

cache-warmup: ## Warm up Symfony cache
	@echo '${BLUE}Warming up cache...${NC}'
	$(DOCKER_COMPOSE) exec php bin/console cache:warmup
	@echo '${GREEN}✓ Cache warmed up${NC}'

cache-warmup-prod: ## Warm up production cache
	@echo '${MAGENTA}Warming up production cache...${NC}'
	$(COMPOSE_PROD) exec php bin/console cache:warmup --env=prod --no-debug
	@echo '${GREEN}✓ Production cache warmed up${NC}'

## Testing

test: ## Run backend tests
	@echo '${BLUE}Running backend tests...${NC}'
	$(DOCKER_COMPOSE) exec php bin/phpunit
	@echo '${GREEN}✓ Tests complete${NC}'

test-coverage: ## Run tests with coverage report
	@echo '${BLUE}Running tests with coverage...${NC}'
	$(DOCKER_COMPOSE) exec php bin/phpunit --coverage-html var/coverage
	@echo '${GREEN}✓ Coverage report: backend/var/coverage/index.html${NC}'

test-frontend: ## Run frontend tests
	@echo '${BLUE}Running frontend tests...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm test -- --watchAll=false
	@echo '${GREEN}✓ Frontend tests complete${NC}'

test-all: test test-frontend ## Run all tests (backend + frontend)

## Logs

logs: ## Show logs for all services (follow mode)
	$(DOCKER_COMPOSE) logs -f

logs-prod: ## Show production logs (follow mode)
	$(COMPOSE_PROD) logs -f

logs-backend: ## Show backend logs (php + nginx)
	$(DOCKER_COMPOSE) logs -f php nginx

logs-frontend: ## Show frontend logs
	$(DOCKER_COMPOSE) logs -f frontend

logs-db: ## Show database logs
	$(DOCKER_COMPOSE) logs -f mysql

## Shell Access

shell: backend-shell ## Open shell in backend container (alias)

backend-shell: ## Open bash shell in backend (PHP) container
	@echo '${BLUE}Opening backend shell...${NC}'
	$(DOCKER_COMPOSE) exec php bash

backend-shell-prod: ## Open bash shell in production backend
	@echo '${MAGENTA}Opening production backend shell...${NC}'
	$(COMPOSE_PROD) exec php bash

frontend-shell: ## Open shell in frontend container
	@echo '${BLUE}Opening frontend shell...${NC}'
	$(DOCKER_COMPOSE) exec frontend sh

nginx-shell: ## Open shell in nginx container
	@echo '${BLUE}Opening nginx shell...${NC}'
	$(DOCKER_COMPOSE) exec nginx sh

db-shell: ## Open MySQL shell
	@echo '${BLUE}Opening database shell...${NC}'
	@if [ -z "$(DB_PASS)" ]; then \
		$(DOCKER_COMPOSE) exec mysql mysql -u root -p; \
	else \
		$(DOCKER_COMPOSE) exec mysql mysql -u $(DB_USER) -p$(DB_PASS) $(DB_NAME); \
	fi

## Package Management

composer-install: ## Install Composer dependencies
	@echo '${BLUE}Installing Composer dependencies...${NC}'
	$(DOCKER_COMPOSE) exec php composer install
	@echo '${GREEN}✓ Composer dependencies installed${NC}'

composer-update: ## Update Composer dependencies
	@echo '${BLUE}Updating Composer dependencies...${NC}'
	$(DOCKER_COMPOSE) exec php composer update
	@echo '${GREEN}✓ Composer dependencies updated${NC}'

composer-require: ## Install a package (usage: make composer-require PKG=vendor/package)
	@if [ -z "$(PKG)" ]; then \
		echo '${RED}Error: Specify package with PKG=vendor/package${NC}'; \
		exit 1; \
	fi
	@echo '${BLUE}Installing $(PKG)...${NC}'
	$(DOCKER_COMPOSE) exec php composer require $(PKG)
	@echo '${GREEN}✓ Package installed${NC}'

npm-install: ## Install npm dependencies
	@echo '${BLUE}Installing npm dependencies...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm install
	@echo '${GREEN}✓ npm dependencies installed${NC}'

npm-update: ## Update npm dependencies
	@echo '${BLUE}Updating npm dependencies...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm update
	@echo '${GREEN}✓ npm dependencies updated${NC}'

npm-add: ## Add npm package (usage: make npm-add PKG=package-name)
	@if [ -z "$(PKG)" ]; then \
		echo '${RED}Error: Specify package with PKG=package-name${NC}'; \
		exit 1; \
	fi
	@echo '${BLUE}Installing $(PKG)...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm install $(PKG)
	@echo '${GREEN}✓ Package installed${NC}'

## Code Quality

lint: lint-backend lint-frontend ## Lint all code (backend + frontend)

lint-backend: ## Lint backend PHP code
	@echo '${BLUE}Linting backend code...${NC}'
	$(DOCKER_COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff || true
	@echo '${GREEN}✓ Backend lint complete${NC}'

lint-frontend: ## Lint frontend code
	@echo '${BLUE}Linting frontend code...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm run lint || true
	@echo '${GREEN}✓ Frontend lint complete${NC}'

format: format-backend format-frontend ## Format all code (backend + frontend)

format-backend: ## Format backend PHP code
	@echo '${BLUE}Formatting backend code...${NC}'
	$(DOCKER_COMPOSE) exec php vendor/bin/php-cs-fixer fix
	@echo '${GREEN}✓ Backend formatted${NC}'

format-frontend: ## Format frontend code  
	@echo '${BLUE}Formatting frontend code...${NC}'
	$(DOCKER_COMPOSE) exec frontend npm run format || true
	@echo '${GREEN}✓ Frontend formatted${NC}'

## Deployment & Frontend Builds

frontend-dev: ## Start frontend in development mode (attached)
	@echo '${BLUE}Starting frontend in development mode...${NC}'
	$(DOCKER_COMPOSE) up frontend

frontend-build: ## Build frontend production bundle
	@echo '${BLUE}Building frontend production bundle...${NC}'
	$(DOCKER_COMPOSE) run --rm frontend npm run build
	@echo '${GREEN}✓ Frontend build complete: frontend/build/${NC}'

deploy-prod: build-prod start-prod migrate-prod cache-warmup-prod ## Full production deployment

## Status & Information

ps: ## Show running containers
	@echo '${CYAN}Running Containers:${NC}'
	$(DOCKER_COMPOSE) ps

ps-prod: ## Show running production containers
	@echo '${CYAN}Running Production Containers:${NC}'
	$(COMPOSE_PROD) ps

status: ## Show detailed application status
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'
	@echo '${CYAN}   Application Status${NC}'
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'
	@echo ''
	@echo '${GREEN}Services:${NC}'
	@$(DOCKER_COMPOSE) ps
	@echo ''
	@echo '${GREEN}Endpoints:${NC}'
	@echo '  ${YELLOW}Frontend (dev):${NC}  http://localhost:3000'
	@echo '  ${YELLOW}Backend API:${NC}     http://localhost:8081'
	@echo '  ${YELLOW}MySQL:${NC}           localhost:3306'
	@echo ''
	@echo '${GREEN}Docker Info:${NC}'
	@docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}" 2>/dev/null || echo "  (stats unavailable)"
	@echo ''
	@echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}'

health: ## Check health of all services
	@echo '${BLUE}Checking service health...${NC}'
	@echo ''
	@docker ps --filter "name=vehicle_" --format "table {{.Names}}\t{{.Status}}"
	@echo ''
	@echo '${CYAN}Health Check Details:${NC}'
	@docker inspect --format='{{.Name}}: {{.State.Health.Status}}' $$(docker ps -q --filter "name=vehicle_") 2>/dev/null || echo "  (No healthcheck info available)"

## Database Backups

backup-db: ## Backup database to backups/ directory
	@echo '${BLUE}Backing up database...${NC}'
	@mkdir -p backups
	@BACKUP_FILE="backups/backup_$$(date +%Y%m%d_%H%M%S).sql"; \
	$(DOCKER_COMPOSE) exec -T mysql mysqldump -u root -p$${MYSQL_ROOT_PASSWORD:-root} $${MYSQL_DATABASE:-vehicle_management} > $$BACKUP_FILE && \
	echo "${GREEN}✓ Database backup created: $$BACKUP_FILE${NC}" || \
	echo "${RED}✗ Backup failed${NC}"

restore-db: ## Restore database (usage: make restore-db FILE=backups/backup.sql)
	@if [ -z "$(FILE)" ]; then \
		echo '${RED}Error: Specify backup file with FILE=backups/backup_file.sql${NC}'; \
		exit 1; \
	fi
	@if [ ! -f "$(FILE)" ]; then \
		echo '${RED}Error: File $(FILE) not found${NC}'; \
		exit 1; \
	fi
	@echo '${BLUE}Restoring database from $(FILE)...${NC}'
	@$(DOCKER_COMPOSE) exec -T mysql mysql -u root -p$${MYSQL_ROOT_PASSWORD:-root} $${MYSQL_DATABASE:-vehicle_management} < $(FILE)
	@echo '${GREEN}✓ Database restored from $(FILE)${NC}'

list-backups: ## List available database backups
	@echo '${CYAN}Available backups:${NC}'
	@ls -lh backups/*.sql 2>/dev/null || echo "  No backups found"

# Default target
.DEFAULT_GOAL := help
