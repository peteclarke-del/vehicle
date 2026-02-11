FROM nginx:alpine

# Install netcat for healthcheck
RUN apk add --no-cache netcat-openbsd

# Bake in the default config (dev compose can override via volume mount)
COPY default.conf /etc/nginx/conf.d/default.conf
