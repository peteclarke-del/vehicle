# Vehicle Management System

A comprehensive vehicle management application built with PHP 8.4, Symfony 6.4, React, MySQL 8, and Docker.

## Features

- **Vehicle Management**: Track multiple vehicles with detailed information (VIN, registration, engine number, V5 document, etc.)
- **Depreciation Calculation**: Support for multiple depreciation methods (straight-line, declining balance, double declining)
- **Fuel Tracking**: Record fuel purchases with date, litres, cost, mileage, and station
- **Parts & Spares**: Track parts purchases, installation dates, and costs
- **Consumables**: Manage consumables like tyres, oils, etc., specific to vehicle types
- **Cost Analysis**: Calculate running costs, cost per mile, fuel consumption, and total cost to date
- **Multi-language Support**: English, Spanish, and French (extensible)
- **Theming**: Light and dark mode support
- **Authentication**: JWT tokens, SSO/SAML, and local credentials

## Tech Stack

### Backend
- PHP 8.4
- Symfony 6.4
- MySQL 8
- Doctrine ORM
- JWT Authentication
- SAML/SSO Support

### Frontend
- React 18
- Material-UI (MUI)
- React Router
- i18next for internationalization
- Axios for API calls

### Infrastructure
- Docker & Docker Compose
- Nginx
- Node.js

## Getting Started

### Prerequisites
- Docker and Docker Compose installed
- Git
- Make (optional, for using Makefile commands)

### Quick Setup with Makefile

The easiest way to get started is using the provided Makefile:

```bash
# Initial setup (builds containers, installs dependencies, creates database)
make setup

# Or use the automated setup script
./setup.sh
```

**Default Admin Credentials:**
- Email: `admin@vehicle.local`
- Password: `changeme`
- **Note:** You will be required to change this password on first login

### Manual Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd vehicle
```

2. Build and start containers:
```bash
make build
make start
# Or: docker-compose up -d
```

3. Install dependencies:
```bash
make install
# Or manually:
# docker-compose exec php composer install
# docker-compose exec frontend npm install
```

4. Generate JWT keys:
```bash
make jwt-keys
```

5. Create database and run migrations:
```bash
make migrate
```

6. Load initial data (vehicle types, consumable types, and admin user):
```bash
make fixtures
```

7. Access the application:
- Frontend: http://localhost:3000
- Backend API: http://localhost:8000/api

## Makefile Commands

The project includes a comprehensive Makefile with many useful commands:

### Setup & Installation
- `make help` - Show all available commands
- `make setup` - Complete initial setup (recommended for first time)
- `make install` - Install backend and frontend dependencies
- `make jwt-keys` - Generate JWT authentication keys

### Service Management
- `make start` - Start all services
- `make stop` - Stop all services
- `make restart` - Restart all services
- `make down` - Stop and remove containers
- `make ps` - Show running containers
- `make status` - Show application status

### Database
- `make migrate` - Run database migrations
- `make fixtures` - Load database fixtures (includes admin user)
- `make db-create` - Create database
- `make db-drop` - Drop database (WARNING: destroys data)
- `make db-reset` - Complete database reset
- `make backup-db` - Backup database to backups/ directory
- `make restore-db FILE=backups/backup.sql` - Restore database from backup

### Development
- `make logs` - Show logs for all services
- `make logs-backend` - Show backend logs only
- `make logs-frontend` - Show frontend logs only
- `make logs-db` - Show database logs only
- `make shell` - Open bash shell in backend container
- `make frontend-shell` - Open shell in frontend container
- `make db-shell` - Open MySQL shell
- `make cache-clear` - Clear Symfony cache

### Build & Deploy
- `make build` - Build Docker containers
- `make frontend-build` - Build frontend for production
- `make production-build` - Complete production build
- `make clean` - Remove containers, volumes, and generated files

### Code Quality
- `make test` - Run tests
- `make lint-backend` - Lint backend code
- `make lint-frontend` - Lint frontend code
- `make format-backend` - Format backend code
- `make format-frontend` - Format frontend code

### Updates
- `make composer-update` - Update Composer dependencies
- `make npm-update` - Update npm dependencies

## Default Users

After running `make fixtures` or `make setup`, a default admin user is created:

- **Email:** admin@vehicle.local
- **Password:** changeme
- **Roles:** ROLE_ADMIN, ROLE_USER

**Important:** You will be prompted to change the password on first login for security.

Admins can also force password changes for other users via the API endpoint:
```bash
POST /api/force-password-change/{userId}
```

## API Endpoints

### Authentication
- POST `/api/register` - Register new user
- POST `/api/login` - Login (returns JWT token)
- GET `/api/me` - Get current user info (includes passwordChangeRequired flag)
- PUT `/api/profile` - Update user profile
- POST `/api/change-password` - Change user password
  - Body: `{ "currentPassword": "old", "newPassword": "new" }`
  - Clears `passwordChangeRequired` flag on success
- POST `/api/force-password-change/{id}` - Force password change for user (Admin only)

### Vehicles
- GET `/api/vehicles` - List all vehicles
- POST `/api/vehicles` - Create vehicle
- GET `/api/vehicles/{id}` - Get vehicle details
- PUT `/api/vehicles/{id}` - Update vehicle
- DELETE `/api/vehicles/{id}` - Delete vehicle
- GET `/api/vehicles/{id}/stats` - Get vehicle statistics

### Fuel Records
- GET `/api/fuel-records?vehicleId={id}` - List fuel records
- POST `/api/fuel-records` - Create fuel record
- PUT `/api/fuel-records/{id}` - Update fuel record
- DELETE `/api/fuel-records/{id}` - Delete fuel record

### Parts
- GET `/api/parts?vehicleId={id}` - List parts
- POST `/api/parts` - Create part
- PUT `/api/parts/{id}` - Update part
- DELETE `/api/parts/{id}` - Delete part

### Consumables
- GET `/api/consumables?vehicleId={id}` - List consumables
- POST `/api/consumables` - Create consumable
- PUT `/api/consumables/{id}` - Update consumable
- DELETE `/api/consumables/{id}` - Delete consumable

### Vehicle Types
- GET `/api/vehicle-types` - List vehicle types
- GET `/api/vehicle-types/{id}/consumable-types` - Get consumable types for vehicle type

## Configuration

### Environment Variables

Backend (.env):
```
DATABASE_URL=mysql://vehicle_user:vehicle_pass@mysql:3306/vehicle_management
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
```

Frontend (.env.local):
```
REACT_APP_API_URL=http://localhost:8080/api
```

## Development

### Backend Development
```bash
# Run Symfony console commands
docker exec -it vehicle_php bin/console <command>

# Clear cache
docker exec -it vehicle_php bin/console cache:clear

# Create new migration
docker exec -it vehicle_php bin/console make:migration
```

### Frontend Development
```bash
cd frontend
npm start  # Runs on http://localhost:3000
```

## Depreciation Methods

1. **Straight Line**: Depreciates evenly over the specified number of years
2. **Declining Balance**: Applies a fixed percentage depreciation rate each year
3. **Double Declining**: Accelerated depreciation at twice the straight-line rate

## Internationalization

The application supports multiple languages with an externalized translation system:

### Current Languages
- English (en)
- Spanish (es)
- French (fr)

### Adding a New Language

1. **Create translation file:**
```bash
mkdir -p frontend/public/locales/de
cp frontend/public/locales/en/translation.json frontend/public/locales/de/
```

2. **Translate the content** in `frontend/public/locales/de/translation.json`

3. **Register the language** in `frontend/src/i18n.js`:
```javascript
{ code: 'de', name: 'German', nativeName: 'Deutsch' }
```

4. **Restart the frontend** - The new language will appear automatically in the language selector

### Translation Files Location
- Frontend: `frontend/public/locales/{lang}/translation.json`
- Backend: `backend/translations/`

For detailed translation management, see [TRANSLATION_GUIDE.md](TRANSLATION_GUIDE.md)

## Security

- JWT tokens for API authentication
- CORS configured for frontend access
- Password hashing with Symfony's password hasher
- SAML/SSO support for enterprise authentication

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Documentation

- **[README.md](README.md)** - Main documentation (this file)
- **[QUICKSTART.md](QUICKSTART.md)** - Quick start guide for rapid setup
- **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Comprehensive project overview
- **[MAKEFILE_REFERENCE.md](MAKEFILE_REFERENCE.md)** - Complete Makefile command reference
- **[TRANSLATION_GUIDE.md](TRANSLATION_GUIDE.md)** - Translation system and language management
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Contribution guidelines

## License

MIT License

## Support

For issues and questions, please open an issue on GitHub.
