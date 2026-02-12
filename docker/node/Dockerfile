# Development stage - for hot reload and development
FROM node:20-alpine AS development

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
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies (including devDependencies needed for build)
RUN npm ci --prefer-offline --no-audit

# Copy source code
COPY . .

# Choose which .env file to bake into the production bundle.
# CRA reads .env.production.local with HIGHER priority than .env,
# so any values in the chosen file override the defaults in .env.
#
#   .env       — production defaults (password policy, API at /api)
#   .env.demo  — demo overrides      (demo mode ON, same policy)
#
# Compose files pass the appropriate file via the ENV_FILE build arg.
ARG ENV_FILE
RUN if [ -n "$ENV_FILE" ]; then \
      if [ -f "$ENV_FILE" ]; then \
        cp "$ENV_FILE" .env.production.local; \
        echo "Using $ENV_FILE as .env.production.local"; \
      else \
        echo "WARNING: ENV_FILE='$ENV_FILE' not found — using .env defaults"; \
      fi; \
    fi && npm run build

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
