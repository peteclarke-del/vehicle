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
```bash
# Using Make (recommended)
make setup

# Or using Docker Compose directly
docker-compose --env-file .env.dev up -d
```

### 4. Access the Application
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8080/api
- **API Documentation**: Available via Symfony routes

### 5. Default Admin Credentials
- **Email**: admin@vehicle.local
- **Password**: changeme *(Change this immediately after first login)*

## 📖 Usage

### Development Workflow

```bash
# Start services
make start

# View logs
make logs

# Run tests
make test

# Access containers
make shell          # PHP container
make frontend-shell # Node container
make db-shell       # MySQL container

# Database operations
make migrate        # Run migrations
make fixtures       # Load test data
make db-create      # Create database
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

### Backend Tests
```bash
make test
# or
docker-compose exec php bin/phpunit
```

### Frontend Tests
```bash
cd frontend
npm test
```

### Code Quality
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

### Production Build
```bash
# Build frontend for production
make production-build

# Configure production environment variables
cp .env .env.production
# Edit .env.production with production values
```

### Docker Production
```bash
# Build production images
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build

# Deploy
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## 🤝 Contributing

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
