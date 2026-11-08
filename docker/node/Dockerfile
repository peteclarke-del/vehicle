# Development stage - for hot reload and development
FROM node:22-alpine AS development

WORKDIR /app

# Copy package files first for better layer caching
COPY package*.json ./

# Install all dependencies including devDependencies
RUN npm ci --prefer-offline --no-audit

# Copy application code
COPY . .

EXPOSE 3000

CMD ["npm", "start"]

# Build stage - creates optimized production build
FROM node:22-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies (including devDependencies needed for build)
RUN npm ci --prefer-offline --no-audit

# Copy source code
COPY . .

# Settings baked into the production bundle, highest priority first:
#   1. Build args below (set by the compose files, e.g. demo mode)
#   2. ENV_FILE - an extra env file copied to .env.production.local
#   3. frontend/.env - local customisation (gitignored)
#   4. frontend/.env.example - tracked defaults, used when .env is missing
#      (fresh clone), so the API path and password policy are never unset.
ARG ENV_FILE
ARG REACT_APP_API_URL
ARG REACT_APP_DEMO_MODE
ARG REACT_APP_PASSWORD_POLICY
RUN set -e; \
    for v in REACT_APP_API_URL REACT_APP_DEMO_MODE REACT_APP_PASSWORD_POLICY; do \
      eval "[ -n \"\${$v}\" ]" || unset "$v"; \
    done; \
    if [ ! -f .env ] && [ -f .env.example ]; then \
      cp .env.example .env; echo "No frontend/.env - using .env.example defaults"; \
    fi; \
    if [ -n "$ENV_FILE" ]; then \
      if [ -f "$ENV_FILE" ]; then \
        cp "$ENV_FILE" .env.production.local; echo "Using $ENV_FILE as .env.production.local"; \
      else \
        echo "WARNING: ENV_FILE='$ENV_FILE' not found - using .env defaults"; \
      fi; \
    fi; \
    npm run build

# Production stage - serve optimized build with nginx
FROM nginx:alpine AS production

# Install netcat for healthcheck
RUN apk add --no-cache netcat-openbsd

# Copy custom nginx configuration if it exists
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copy built application from builder stage
COPY --from=builder /app/build /usr/share/nginx/html

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD nc -z localhost 80 || exit 1

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
