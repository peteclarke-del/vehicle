# Vehicle Management System

A comprehensive web application for managing personal or fleet vehicles, built with modern technologies and designed for ease of use.

## 🚗 Features

### Vehicle Management
- **Complete Vehicle Profiles**: Track make, model, year, specifications, and images
- **Fuel Records**: Log fuel purchases, calculate efficiency, and track costs
- **Service History**: Maintain detailed service records with parts and labor
- **MOT Records**: Track MOT test results and expiry dates
- **Insurance Management**: Manage multiple insurance policies per vehicle
- **Road Tax**: Monitor road tax status and renewal dates
- **Parts Inventory**: Track vehicle parts with suppliers and pricing
- **Consumables**: Manage fluids, filters, and other consumable items

### User Experience
- **Modern UI**: Clean, responsive interface built with Material-UI
- **Multi-language Support**: English and other languages via i18next
- **Dark/Light Themes**: Customizable user interface themes
- **Mobile Responsive**: Works seamlessly on desktop and mobile devices
- **Real-time Updates**: Live data synchronization

### Technical Features
- **Secure Authentication**: JWT-based authentication with role management
- **API-First Design**: RESTful API with comprehensive documentation
- **Docker Development**: Containerized development environment
- **Automated Testing**: Unit and integration tests
- **Database Migrations**: Version-controlled database schema
- **File Uploads**: Support for vehicle images and documents

## 🛠️ Tech Stack

### Backend
- **Framework**: Symfony 6.4
- **Language**: PHP 8.3+
- **Database**: MySQL 8.0
- **ORM**: Doctrine ORM
- **Authentication**: JWT (LexikJWTAuthenticationBundle)
- **API**: RESTful with Symfony Serializer
- **Testing**: PHPUnit

### Frontend
- **Framework**: React 18
- **UI Library**: Material-UI (MUI)
- **Routing**: React Router
- **HTTP Client**: Axios
- **Internationalization**: i18next
- **Build Tool**: Create React App
- **Charts**: MUI X Charts

### Infrastructure
- **Containerization**: Docker & Docker Compose
- **Web Server**: Nginx
- **Reverse Proxy**: Nginx for API routing
- **Development Tools**: Makefile for common tasks

## 📋 Prerequisites

- **Docker & Docker Compose**: For containerized development
- **Git**: For version control
- **Make**: For running development commands (optional but recommended)

## 🚀 Quick Start

### 1. Clone the Repository
```bash
git clone <repository-url>
cd vehicle-management-system
```

### 2. Environment Setup
```bash
# Copy and configure environment files
cp .env.dev .env.dev.local  # For development with real values
# Edit .env.dev.local with your actual database credentials and API keys
```

### 3. Start the Application

**Option A: Using Make (Recommended)**
```bash
make setup              # Complete setup (build, install, migrate, load fixtures)
```

**Option B: Using Docker Compose Directly**
```bash
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec php bin/console doctrine:fixtures:load --no-interaction
```

**Option C: Manual Step-by-Step**
```bash
make build              # Build containers
make start              # Start services
make install            # Install dependencies
make jwt-keys           # Generate JWT keys
make migrate            # Run database migrations
make fixtures           # Load sample data
```

### 4. Access the Application

**Development Environment**
- **Frontend**: http://localhost:3000 (React dev server with hot reload)
- **Backend API**: http://localhost:8081/api
- **MySQL**: localhost:3306

**Production Environment** (if using `make start-prod`)
- **Frontend**: http://localhost:80 (nginx-served static build)
- **Backend API**: http://localhost:8081/api
- **MySQL**: localhost:3306

### 5. Default Admin Credentials
- **Email**: admin@vehicle.local
- **Password**: changeme *(Change this immediately after first login)*

## 📖 Usage

### Using the Makefile

The project includes a comprehensive Makefile with commands for all common development and deployment tasks. Run `make help` to see all available commands organized by category.

#### Quick Reference

**Initial Setup**
```bash
make setup          # Complete development setup (build, install, migrate, fixtures)
make setup-prod     # Complete production setup
```

**Development Commands**
```bash
make start          # Start development services
make stop           # Stop all services
make restart        # Restart all services
make logs           # View logs for all services (follow mode)
make status         # Show detailed application status with resource usage
```

**Building**
```bash
make build                # Build all containers (development)
make build-prod           # Build production containers (multi-stage)
make build-no-cache       # Build without cache
make build-frontend-prod  # Build frontend for production (nginx-served)
make build-backend        # Build backend only
```

**Database Operations**
```bash
make migrate        # Run database migrations
make fixtures       # Load test data (development only)
make db-reset       # Drop, recreate, migrate, and load fixtures
make backup-db      # Backup database to backups/ directory
make restore-db FILE=backups/backup.sql  # Restore database from backup
```

**Testing**
```bash
make test              # Run backend tests
make test-frontend     # Run frontend tests
make test-all          # Run all tests
make test-coverage     # Run tests with coverage report
```

**Shell Access**
```bash
make shell             # Open PHP container shell (alias for backend-shell)
make backend-shell     # Open bash shell in PHP container
make frontend-shell    # Open shell in frontend container
make nginx-shell       # Open shell in nginx container
make db-shell          # Open MySQL shell
```

**Package Management**
```bash
make composer-install               # Install PHP dependencies
make composer-require PKG=vendor/package  # Install specific PHP package
make npm-install                    # Install JavaScript dependencies
make npm-add PKG=package-name      # Install specific npm package
```

**Code Quality**
```bash
make lint              # Lint all code (backend + frontend)
make lint-backend      # Lint PHP code
make lint-frontend     # Lint JavaScript code
make format            # Format all code
make format-backend    # Format PHP code
make format-frontend   # Format JavaScript code
```

**Cache Management**
```bash
make cache-clear       # Clear Symfony cache (development)
make cache-warmup      # Warm up Symfony cache
make cache-clear-prod  # Clear production cache
```

**Production Deployment**
```bash
make build-prod        # Build production images
make start-prod        # Start production services
make deploy-prod       # Full deployment pipeline (build, start, migrate, warmup)
make stop-prod         # Stop production services
```

**Cleanup**
```bash
make clean             # Remove containers, volumes, and build artifacts
make clean-all         # Deep clean including Docker images
make prune             # Remove all unused Docker resources
```

**Monitoring & Status**
```bash
make ps                # Show running containers
make health            # Check health status of all services
make logs-backend      # Show backend logs (php + nginx)
make logs-frontend     # Show frontend logs
make logs-db           # Show database logs
make list-backups      # List available database backups
```

#### Environment-Specific Commands

Most commands have environment-specific variants:

- **Development** (default): `make start`, `make build`, `make install`
- **Production**: `make start-prod`, `make build-prod`, `make install-prod`

Examples:
```bash
# Development workflow
make build              # Build dev containers
make start              # Start dev services (frontend on :3000)
make migrate            # Run migrations
make test               # Run tests

# Production workflow
make build-prod         # Build prod containers (multi-stage)
make start-prod         # Start prod services (frontend on :80)
make migrate-prod       # Run migrations in production
make cache-warmup-prod  # Warm up production cache
```

#### Multi-Stage Frontend Build

The frontend supports multiple build stages:
- **Development**: Hot reload with source maps (port 3000)
- **Production**: Optimized nginx-served static build (port 80)

```bash
# Development build (default)
make build-frontend-dev

# Production build (multi-stage with nginx)
make build-frontend-prod
```

#### Help System

View all available commands grouped by category:
```bash
make help
```

This displays:
- 🏗️ Build Commands
- 🚀 Service Commands
- 🔧 Development Commands
- 🗄️ Database Commands
- 🧪 Testing & Logs
- 🐚 Shell Access
- 📦 Utility Commands

### Development Workflow

### Development Workflow

**Daily Development**
```bash
# Start your day
make start              # Start all services
make logs               # Monitor logs in real-time

# Make changes to code (auto-reloads)

# Run tests frequently
make test               # Backend tests
make test-frontend      # Frontend tests

# View application status
make status             # Detailed status with resource usage
```

**Working with Database**
```bash
# Create new migration
docker-compose exec php bin/console make:migration

# Run migrations
make migrate

# Reset database (useful for testing)
make db-reset

# Backup before major changes
make backup-db
```

**Code Quality Checks**
```bash
# Before committing
make lint               # Check code style
make format             # Auto-fix style issues
make test-all           # Run all tests
```

**Troubleshooting**
```bash
# View service logs
make logs-backend       # Backend issues
make logs-frontend      # Frontend issues
make logs-db            # Database issues

# Check service health
make health

# Access container for debugging
make backend-shell
make frontend-shell

# Fresh start (nuclear option)
make clean-all
make setup
```

### Configuration

#### Password Policy
Configure password requirements in `frontend/.env`:
```bash
# Option 1: Strict (uppercase, lowercase, numbers, special chars)
REACT_APP_PASSWORD_POLICY=^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$

# Option 2: Moderate (uppercase, lowercase, numbers only)
REACT_APP_PASSWORD_POLICY=^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$
```

#### Database Configuration
Edit `.env.dev` or `.env` with your database settings:
```bash
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_DATABASE=vehicle_management
MYSQL_USER=vehicle_user
MYSQL_PASSWORD=your_db_password
```

#### API Keys (Optional)
Configure external API integrations:
```bash
# DVSA MOT History API
DVSA_CLIENT_ID=your_client_id
DVSA_CLIENT_SECRET=your_client_secret
DVSA_API_KEY=your_api_key

# eBay API
EBAY_APP_ID=your_app_id
EBAY_CLIENT_ID=your_client_id

# API Ninjas
API_NINJAS_KEY=your_api_key
```

## 🏗️ Project Structure

```
vehicle-management-system/
├── backend/                 # Symfony API application
│   ├── src/
│   │   ├── Controller/      # API controllers
│   │   ├── Entity/          # Doctrine entities
│   │   ├── Repository/      # Data access layer
│   │   └── Service/         # Business logic
│   ├── config/              # Symfony configuration
│   ├── migrations/          # Database migrations
│   └── tests/               # Backend tests
├── frontend/                # React application
│   ├── src/
│   │   ├── components/      # Reusable UI components
│   │   ├── pages/           # Page components
│   │   ├── contexts/        # React contexts
│   │   └── services/        # API services
│   ├── public/
│   │   ├── locales/         # Translation files
│   │   └── images/          # Static assets
│   └── .env                 # Frontend configuration
├── docker/                  # Docker configurations
│   ├── nginx/               # Web server config
│   ├── php/                 # PHP-FPM config
│   └── node/                # Node.js config
├── docs/                    # Documentation
├── .env*                    # Environment files
├── docker-compose.yml       # Docker services
├── Makefile                 # Development commands
└── setup.sh                 # Initial setup script
```

## 🧪 Testing

### Running Tests

**Backend Tests**
```bash
make test                # Run all backend tests
make test-coverage       # Generate coverage report (backend/var/coverage/)
```

**Frontend Tests**
```bash
make test-frontend       # Run frontend tests
```

**All Tests**
```bash
make test-all            # Run both backend and frontend tests
```

### Manual Test Execution

**Backend Tests (PHPUnit)**
```bash
# Using Make
make test

# Using Docker Compose
docker-compose exec php bin/phpunit

# Specific test
docker-compose exec php bin/phpunit tests/Controller/VehicleControllerTest.php

# With coverage
make test-coverage
```

**Frontend Tests (Jest)**
```bash
# Using Make
make test-frontend

# Using Docker Compose
docker-compose exec frontend npm test -- --watchAll=false

# Interactive watch mode
cd frontend
npm test
```

### Code Quality Checks

**Linting**
```bash
make lint              # Lint all code
make lint-backend      # PHP CS Fixer (dry-run)
make lint-frontend     # ESLint
```

**Auto-Formatting**
```bash
# PHP code style
make lint-backend

# JavaScript linting
make lint-frontend

# Auto-fix issues
make format-backend
make format-frontend
```

## 🚢 Deployment

### Production Deployment with Make

The Makefile provides a streamlined production deployment workflow:

```bash
# Complete production deployment
make deploy-prod

# Or step-by-step:
make build-prod         # Build production images (multi-stage)
make start-prod         # Start production services
make migrate-prod       # Run database migrations
make cache-warmup-prod  # Warm up application cache
```

### Production Configuration

1. **Create Production Environment File**
```bash
cp .env .env.production
# Edit .env.production with production values:
# - Secure passwords
# - Production database credentials
# - JWT keys with proper permissions
# - API keys for external services
```

2. **Build Production Images**
```bash
make build-prod
```

This builds:
- **Frontend**: Multi-stage build → nginx-served static files (optimized, minified)
- **Backend**: PHP-FPM with production dependencies (--no-dev --optimize-autoloader)
- **Nginx**: Custom image with healthcheck support

3. **Deploy**
```bash
make start-prod
```

### Docker Production Details

The production setup uses `docker-compose.yml` + `docker-compose.prod.yml` with:

**Frontend (Multi-Stage Build)**
- Stage 1: Build React app (`npm run build`)
- Stage 2: Serve with nginx
- Port: 80 (instead of 3000)
- No source code mounted (immutable deployment)
- Resource limits: 128M memory, 0.5 CPU
- Can be scaled: `docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --scale frontend=2`

**Backend Optimizations**
- Production Composer flags: `--no-dev --optimize-autoloader`
- APP_ENV=prod, APP_DEBUG=0
- Tighter file permissions (JWT keys: 600)
- Resource limits: 512M memory, 1.0 CPU
- Scalable with load balancing

**Security Hardening**
- Read-only root filesystems
- No new privileges
- Security headers in nginx
- Non-root users
- Secrets via environment variables only

### Scaling Production Services

```bash
# Scale specific services
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --scale php=2 --scale frontend=2

# Check scaled services
make ps-prod
```

### Production Monitoring
### Production Monitoring

```bash
make status             # Detailed status with Docker stats
make health             # Check service health
make logs-prod          # View production logs
make ps-prod            # List production containers
```

### Production Database Backups

```bash
# Automated backups
make backup-db          # Creates timestamped backup in backups/

# List available backups
make list-backups

# Restore from backup
make restore-db FILE=backups/backup_20260128_120000.sql
```

### Rollback Strategy

```bash
# Stop production services
make stop-prod

# Restore previous backup
make restore-db FILE=backups/backup_before_deployment.sql

# Restart with previous image tags
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### Environment Variables for Production

```bash
# Required
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL=mysql://user:password@mysql:3306/vehicle_db
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<strong-passphrase>

# Optional but recommended
CORS_ALLOW_ORIGIN=^https?://(yourdomain\.com)(:[0-9]+)?$
MYSQL_ROOT_PASSWORD=<strong-root-password>
```

### Traditional Deployment (Non-Docker)

If deploying without Docker:

```bash
# Frontend build for production
cd frontend
npm install
npm run build
# Deploy build/ directory to web server

# Backend setup
cd backend
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:warmup --env=prod
```

## 📊 Docker Compose Architecture

The project uses Docker Compose v3.9 with multi-stage builds and environment-specific configurations:

**Files:**
- `docker-compose.yml` - Base configuration (required)
- `docker-compose.override.yml` - Development overrides (auto-applied in dev)
- `docker-compose.prod.yml` - Production overrides (manual with `-f` flag)

**Services:**
- **mysql**: MySQL 8.0 with healthcheck
- **php**: PHP 8.4-FPM with Xdebug (dev only)
- **nginx**: Nginx Alpine with custom config and healthcheck
- **frontend**: Node 20 (dev) or Nginx (prod)

**Networks:**
- `vehicle_network`: Bridge network for service communication

**Volumes:**
- `mysql_data`: Persistent database storage
- `backend_var`: Production cache/logs (prod only)
- `backend_uploads`: Uploaded files (prod only)

**Resource Limits:**
Development and production have different resource allocations. See [docker/README.md](docker/README.md) for details.

**Build Context Optimization:**
`.dockerignore` files exclude unnecessary files from build contexts:
- Root: General exclusions
- `docker/php/.dockerignore`: PHP build exclusions  
- `frontend/.dockerignore`: Frontend build exclusions

This reduces build time by excluding node_modules, vendor, tests, docs, and IDE configurations.

## � Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes and add tests
4. Run the test suite: `make test`
5. Commit your changes: `git commit -am 'Add your feature'`
6. Push to the branch: `git push origin feature/your-feature`
7. Submit a pull request

### Development Guidelines
- Follow PSR-12 for PHP code
- Use ESLint configuration for JavaScript
- Write tests for new features
- Update documentation for API changes
- Use conventional commit messages

## 📄 API Documentation

The API follows RESTful conventions with JSON responses. Key endpoints:

- `GET /api/vehicles` - List vehicles
- `POST /api/vehicles` - Create vehicle
- `GET /api/vehicles/{id}` - Get vehicle details
- `PUT /api/vehicles/{id}` - Update vehicle
- `DELETE /api/vehicles/{id}` - Delete vehicle

Authentication required for all endpoints using JWT tokens.

## 🔒 Security

- JWT tokens with configurable expiration
- Password hashing with Symfony's security component
- CORS configuration for cross-origin requests
- Input validation and sanitization
- SQL injection prevention via Doctrine ORM
- XSS protection in React components

## 📊 Database Schema

The application uses the following main entities:
- **User**: System users with authentication
- **Vehicle**: Vehicle information and specifications
- **FuelRecord**: Fuel purchase tracking
- **ServiceRecord**: Maintenance history
- **InsurancePolicy**: Insurance coverage details
- **MotRecord**: MOT test results
- **RoadTax**: Road tax information
- **Part**: Spare parts inventory
- **Consumable**: Fluids and consumables

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check MySQL container
make logs-db

# Restart database
docker-compose restart mysql
```

**Frontend Not Loading**
```bash
# Check Node.js container
make logs-frontend

# Rebuild frontend
make clean
make setup
```

**Permission Issues**
```bash
# Fix file permissions
sudo chown -R $USER:$USER .
```

**Port Conflicts**
```bash
# Check what's using ports
lsof -i :3000
lsof -i :8080
lsof -i :3306

# Change ports in docker-compose.yml if needed
```

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Symfony framework and community
- React and Material-UI teams
- Docker and the containerization community
- All contributors and users

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation in the `docs/` directory
- Review the Makefile for available commands

---

**Happy vehicle managing!** 🚗✨
