# Makefile Quick Reference

## 🚀 Getting Started

```bash
make setup    # Complete initial setup (first time only)
make start    # Start all services
make stop     # Stop all services
```

## 📦 Installation & Setup

| Command | Description |
|---------|-------------|
| `make setup` | Complete initial setup (build + install + database) |
| `make build` | Build all Docker containers |
| `make install` | Install backend and frontend dependencies |
| `make jwt-keys` | Generate JWT authentication keys |

## 🔧 Service Management

| Command | Description |
|---------|-------------|
| `make start` | Start all services in background |
| `make stop` | Stop all services |
| `make restart` | Restart all services |
| `make down` | Stop and remove all containers |
| `make ps` | Show running containers |
| `make status` | Show application status with endpoints |
| `make clean` | Remove containers, volumes, and generated files |

## 🗄️ Database Operations

| Command | Description |
|---------|-------------|
| `make migrate` | Run database migrations |
| `make fixtures` | Load fixtures (vehicle types + admin user) |
| `make db-create` | Create database |
| `make db-drop` | Drop database (⚠️ destroys all data) |
| `make db-reset` | Complete database reset |
| `make db-shell` | Open MySQL shell |
| `make backup-db` | Backup database to backups/ directory |
| `make restore-db FILE=backups/backup.sql` | Restore from backup |

## 🔍 Development & Debugging

| Command | Description |
|---------|-------------|
| `make logs` | Show logs for all services |
| `make logs-backend` | Show backend logs only |
| `make logs-frontend` | Show frontend logs only |
| `make logs-db` | Show database logs only |
| `make shell` | Open bash shell in backend container |
| `make backend-shell` | Open bash shell in backend container |
| `make frontend-shell` | Open shell in frontend container |
| `make cache-clear` | Clear Symfony cache |

## 🏗️ Build & Deploy

| Command | Description |
|---------|-------------|
| `make frontend-dev` | Start frontend in development mode |
| `make frontend-build` | Build frontend for production |
| `make production-build` | Complete production build |

## 🧪 Testing & Quality

| Command | Description |
|---------|-------------|
| `make test` | Run all tests |
| `make lint-backend` | Lint backend code (dry-run) |
| `make lint-frontend` | Lint frontend code |
| `make format-backend` | Format backend code (fix issues) |
| `make format-frontend` | Format frontend code |

## 📦 Package Management

| Command | Description |
|---------|-------------|
| `make composer-update` | Update Composer dependencies |
| `make npm-update` | Update npm dependencies |

## 🆘 Help

```bash
make help    # Show all available commands with descriptions
```

## 📍 Default Endpoints

After running `make start`:
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **MySQL**: localhost:3306

## 👤 Default Admin User

After running `make fixtures` or `make setup`:
- **Email**: admin@vehicle.local
- **Password**: changeme (must be changed on first login)
- **Roles**: ROLE_ADMIN, ROLE_USER

## 💡 Common Workflows

### First Time Setup
```bash
make setup
```

### Daily Development
```bash
make start          # Start services
make logs           # Monitor logs
make shell          # Access backend shell
make frontend-dev   # Run frontend dev server
```

### Database Changes
```bash
make db-reset       # Reset database
make fixtures       # Reload fixtures
```

### Cleanup
```bash
make stop           # Stop services
make clean          # Remove everything
```

### Production Deployment
```bash
make production-build
```

### Backup & Restore
```bash
make backup-db                              # Create backup
make restore-db FILE=backups/backup.sql     # Restore backup
```

## ⚡ Power User Tips

1. **Chain commands**: Most commands can be chained
   ```bash
   make stop && make clean && make setup
   ```

2. **Quick restart with DB reset**:
   ```bash
   make db-reset && make restart
   ```

3. **View specific service logs**:
   ```bash
   make logs-backend  # Only backend
   make logs-db       # Only database
   ```

4. **Quick shell access**:
   ```bash
   make shell         # Backend
   make db-shell      # MySQL
   ```

5. **Monitor everything**:
   ```bash
   make logs          # Follow all logs
   ```

## 🐛 Troubleshooting

### Services won't start
```bash
make down
make clean
make setup
```

### Database issues
```bash
make db-reset
```

### Cache issues
```bash
make cache-clear
```

### JWT token issues
```bash
make jwt-keys
make restart
```

### View detailed status
```bash
make status
make ps
```

## 📚 Additional Resources

- [README.md](README.md) - Full documentation
- [QUICKSTART.md](QUICKSTART.md) - Quick start guide
- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Project overview
- [CONTRIBUTING.md](CONTRIBUTING.md) - Contribution guidelines
