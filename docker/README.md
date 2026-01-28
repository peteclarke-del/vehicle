# Docker Build Guide

## Overview

This project uses Docker Compose with multi-stage builds and environment-specific configurations.

## Docker Compose Files

- **docker-compose.yml** - Base configuration (required)
- **docker-compose.override.yml** - Development overrides (auto-applied)
- **docker-compose.prod.yml** - Production overrides (manual)

## Build Stages

### Frontend (Multi-Stage Build)

The frontend Dockerfile supports three stages:

1. **development** - Hot reload with source maps (default)
2. **builder** - Compiles optimized production build
3. **production** - Serves static build via nginx

### Backend (PHP-FPM)

Single-stage build with:
- PHP 8.4-FPM
- Composer
- Xdebug (development only)
- Automatic migrations and fixtures

### Nginx (Backend Proxy)

Custom nginx:alpine with netcat for healthchecks.

## Usage

### Development (Default)

```bash
# Start all services in development mode
docker-compose up -d

# Frontend runs with hot reload on port 3000
# Backend API on port 8081
```

### Production Build

```bash
# Build and run production services
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Frontend served as static build on port 80
# Backend API still on port 8081
# Uses nginx for both frontend and backend
```

### Build Specific Service

```bash
# Build only frontend in production mode
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build frontend

# Build only backend
docker-compose build php
```

## .dockerignore Files

Three .dockerignore files optimize build contexts:

- **/.dockerignore** - Root level exclusions
- **/docker/php/.dockerignore** - PHP build exclusions
- **/frontend/.dockerignore** - Node build exclusions

This reduces build time by excluding:
- node_modules
- vendor directories
- test files
- documentation
- IDE configurations
- Git metadata

## Healthchecks

All services have healthchecks:

- **MySQL**: `mysqladmin ping`
- **PHP**: `php-fpm -t`
- **Nginx**: `nc -z localhost 80` (TCP check)
- **Frontend (prod)**: `nc -z localhost 80` (TCP check)

## Resource Limits

### Development
- MySQL: 512M / 0.5 CPU
- PHP: 256M / 0.5 CPU
- Nginx: 128M / 0.25 CPU
- Frontend: 2G / 1.5 CPU

### Production
- MySQL: 1G / 1.0 CPU (2 replicas possible)
- PHP: 512M / 1.0 CPU (2 replicas)
- Nginx: 256M / 0.5 CPU (2 replicas)
- Frontend: 128M / 0.5 CPU (2 replicas)

## Security Features

- ✅ No privileged containers
- ✅ Read-only root filesystems (where possible)
- ✅ Security headers in nginx
- ✅ Non-root users (www-data for PHP)
- ✅ Secrets via environment variables
- ✅ Resource limits prevent DoS

## Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Required variables:
- `MYSQL_ROOT_PASSWORD`
- `MYSQL_DATABASE`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `DATABASE_URL`
- `APP_SECRET`
- `JWT_PASSPHRASE`

## Volumes

### Development
- Source code mounted for hot reload
- Vendor/node_modules in anonymous volumes

### Production
- No source mounts
- Persistent volumes for uploads and cache
- Read-only application code

## Upgrade from v3.8 to v3.9

Docker Compose v3.9 adds:
- Better build caching
- Improved healthcheck syntax
- Enhanced resource management

No breaking changes - existing v3.8 files are compatible.
