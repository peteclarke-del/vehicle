# Vehicle Management System

A comprehensive web application for managing personal or fleet vehicles, built with modern technologies and designed for ease of use.

## Features

### Vehicle Management
- **Complete Vehicle Profiles**: Track make, model, year, specifications, and images
- **Fuel Records**: Log fuel purchases, calculate efficiency (MPG/km/l), and track costs
- **Service History**: Maintain detailed service records with parts, consumables, and labour
- **MOT Records**: Track MOT test results, advisories, failures, and expiry dates
- **Insurance Management**: Manage multiple insurance policies per vehicle
- **Road Tax**: Monitor road tax status and renewal dates
- **Parts Inventory**: Track vehicle parts with suppliers, pricing, and warranty information
- **Consumables**: Manage fluids, filters, and other consumable items with replacement intervals
- **Todo Lists**: Create vehicle-specific maintenance tasks linked to parts and consumables
- **Depreciation Tracking**: Multiple depreciation methods (straight-line, declining balance, automotive standard)
- **Cost Analysis**: Per-vehicle and total fleet cost breakdowns

### External Integrations
- **DVLA Lookup**: Auto-populate vehicle details from UK registration numbers
- **DVSA MOT History**: Import complete MOT history from government records
- **VIN Decoding**: Decode Vehicle Identification Numbers for specifications
- **Receipt OCR**: Extract data from fuel receipt images
- **URL Scraping**: Scrape part information from supplier websites

### Reporting
- **Custom Reports**: Generate XLSX and PDF reports from JSON templates
- **Cost Reports**: Fuel, service, and total cost analysis
- **Vehicle Overview**: Comprehensive single-vehicle summaries

### User Experience
- **Modern UI**: Clean, responsive interface built with Material-UI
- **Multi-language Support**: Full internationalisation via i18next
- **Dark/Light Themes**: User-selectable interface themes
- **Distance Units**: Support for both miles and kilometres
- **Mobile Responsive**: Works on desktop, tablet, and mobile devices
- **Real-time Notifications**: SSE-based notifications for MOT, insurance, and tax expiries

### Technical Features
- **Secure Authentication**: JWT-based authentication with role management
- **API-First Design**: RESTful API with comprehensive documentation
- **Docker Development**: Fully containerised development environment
- **Import/Export**: Full data export/import including attachments
- **Automated Testing**: PHPUnit and Jest test suites
- **Database Migrations**: Version-controlled schema management

## Tech Stack

### Backend
- **Framework**: Symfony 6.4
- **Language**: PHP 8.3+
- **Database**: MySQL 8.0
- **ORM**: Doctrine ORM
- **Caching**: Redis 7
- **Authentication**: JWT (LexikJWTAuthenticationBundle)
- **Testing**: PHPUnit 10

### Frontend
- **Framework**: React 18
- **UI Library**: Material-UI (MUI) 5
- **Routing**: React Router 6
- **HTTP Client**: Axios
- **Internationalisation**: i18next
- **Charts**: MUI X Charts
- **Testing**: Jest, React Testing Library

### Infrastructure
- **Containerisation**: Docker and Docker Compose
- **Web Server**: Nginx
- **Development Tools**: Makefile with comprehensive commands

## Prerequisites

- **Docker and Docker Compose**: For containerised development
- **Git**: For version control
- **Make**: For running development commands (recommended)

## Quick Start

### 1. Clone the Repository
```bash
git clone <repository-url>
cd vehicle-management-system
```

### 2. Environment Setup
```bash
# Copy environment file
cp .env.example .env
# Edit .env with your database credentials and API keys
```

### 3. Start the Application

**Using Make (Recommended)**
```bash
make setup              # Complete setup (build, install, migrate, load fixtures)
```

**Manual Steps**
```bash
make build              # Build containers
make start              # Start services
make install            # Install dependencies
make jwt-keys           # Generate JWT keys
make migrate            # Run database migrations
make fixtures           # Load sample data
```

### 4. Access the Application

| Service | Development URL | Production URL |
|---------|----------------|----------------|
| Frontend | http://localhost:3000 | http://localhost:80 |
| Backend API | http://localhost:8081/api | http://localhost:8081/api |
| MySQL | localhost:3306 | localhost:3306 |

### 5. Default Credentials
- **Email**: admin@vehicle.local
- **Password**: changeme

Change these immediately after first login.

## Usage

### Makefile Commands

Run `make help` to see all available commands. Key commands:

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

## Project Structure

```
vehicle-management-system/
├── backend/                 # Symfony API application
│   ├── src/
│   │   ├── Controller/      # API controllers
│   │   │   └── Trait/       # Reusable controller traits
│   │   ├── Entity/          # Doctrine entities
│   │   └── Service/         # Business logic services
│   │       └── Trait/       # Reusable service traits
│   ├── config/              # Symfony configuration
│   ├── migrations/          # Database migrations
│   └── tests/               # PHPUnit tests
├── frontend/                # React application
│   ├── src/
│   │   ├── components/      # Reusable UI components
│   │   ├── contexts/        # React context providers
│   │   ├── hooks/           # Custom React hooks
│   │   ├── pages/           # Page components
│   │   ├── reports/         # Report templates (JSON)
│   │   └── utils/           # Utility functions
│   └── public/
│       └── locales/         # Translation files
├── docker/                  # Docker configurations
│   ├── nginx/               # Web server config
│   ├── php/                 # PHP-FPM config
│   └── node/                # Node.js config
├── docs/                    # Documentation
│   ├── FRONTEND_LIBRARIES.md
│   ├── BACKEND_LIBRARIES.md
│   ├── API_REFERENCE.md
│   └── AI_CONTEXT.md
├── uploads/                 # User file uploads
├── docker-compose.yml       # Docker services
├── Makefile                 # Development commands
└── README.md
```

## Testing

### Running Tests

```bash
make test                # Run backend tests
make test-frontend       # Run frontend tests
make test-all            # Run all tests
make test-coverage       # Generate coverage report
```

### Code Quality

```bash
make lint                # Lint all code
make format              # Auto-fix style issues
```

## Deployment

### Production Deployment

```bash
make deploy-prod         # Full deployment (build, start, migrate, warmup)
```

### Step-by-Step

```bash
make build-prod          # Build production images
make start-prod          # Start services
make migrate-prod        # Run migrations
make cache-warmup-prod   # Warm up cache
```

### Production Features

- Multi-stage Docker builds for optimised images
- Nginx-served static frontend build
- Resource limits and security hardening
- Horizontal scaling support

See the deployment section below for detailed configuration.

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

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make changes and add tests
4. Run tests: `make test-all`
5. Run linting: `make lint`
6. Commit: `git commit -am 'Add your feature'`
7. Push: `git push origin feature/your-feature`
8. Submit a pull request

### Guidelines

- Follow PSR-12 for PHP code
- Use ESLint configuration for JavaScript
- Write tests for new features
- Update documentation for API changes
- Use the common hooks and traits documented in `docs/`

## 📄 API Documentation

The API follows RESTful conventions with JSON responses. Full documentation is available in [docs/API_REFERENCE.md](docs/API_REFERENCE.md).

Key endpoint groups:
- `/api/vehicles` - Vehicle CRUD and statistics
- `/api/fuel-records` - Fuel purchase tracking
- `/api/service-records` - Service history
- `/api/mot-records` - MOT test records
- `/api/parts` - Parts inventory
- `/api/consumables` - Consumable items
- `/api/insurance/policies` - Insurance management
- `/api/road-tax` - Road tax records
- `/api/attachments` - File uploads
- `/api/reports` - Report generation

Authentication required for all endpoints using JWT tokens.

## Documentation

Comprehensive documentation is available in the `docs/` folder:

| Document | Description |
|----------|-------------|
| [FRONTEND_LIBRARIES.md](docs/FRONTEND_LIBRARIES.md) | React hooks, utilities, and contexts |
| [BACKEND_LIBRARIES.md](docs/BACKEND_LIBRARIES.md) | PHP traits, services, and patterns |
| [API_REFERENCE.md](docs/API_REFERENCE.md) | Complete REST API documentation |
| [AI_CONTEXT.md](docs/AI_CONTEXT.md) | Application overview for AI assistants |

## 🔒 Security

- JWT tokens with configurable expiration
- Password hashing with Symfony's security component
- CORS configuration for cross-origin requests
- Input validation and sanitization
- SQL injection prevention via Doctrine ORM
- XSS protection in React components

## Database Schema

The application uses the following main entities:

| Entity | Description |
|--------|-------------|
| User | User accounts with authentication |
| UserPreference | User preferences (key-value) |
| Vehicle | Vehicle records and specifications |
| VehicleType | Vehicle categories (Car, Motorcycle, etc.) |
| VehicleImage | Vehicle photos |
| FuelRecord | Fuel purchase tracking |
| ServiceRecord | Service history with items |
| MotRecord | MOT test results |
| Part | Spare parts inventory |
| PartCategory | Part categorisation |
| Consumable | Fluids and consumables |
| ConsumableType | Consumable types |
| InsurancePolicy | Insurance policies (many-to-many with vehicles) |
| RoadTax | Road tax records |
| Attachment | File attachments |
| Specification | Vehicle specifications |
| Todo | Maintenance task lists |
| Report | Generated reports |

See [docs/AI_CONTEXT.md](docs/AI_CONTEXT.md) for detailed entity relationships.

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Database connection failed | `make logs-db` then `docker-compose restart mysql` |
| Frontend not loading | `make logs-frontend` then `make clean && make setup` |
| Permission issues | `sudo chown -R $USER:$USER .` |
| Port conflicts | Check `lsof -i :3000` and modify docker-compose.yml |
| 401 Unauthorized errors | JWT token expired; re-login |
| Attachment not appearing | Ensure `finalizeAttachmentLink()` is called after flush |

For detailed troubleshooting, see [docs/AI_CONTEXT.md](docs/AI_CONTEXT.md#common-issues-and-solutions).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- Create an issue on GitHub
- Check documentation in `docs/`
- Review the Makefile: `make help`
