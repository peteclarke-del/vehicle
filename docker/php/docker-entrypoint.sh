#!/usr/bin/env bash
set -euo pipefail

TARGET_DIR=/var/www/html
USER=www-data
GROUP=www-data

fix_perms() {
  local dir="$1"

  if [ ! -d "$dir" ]; then
    return
  fi

  echo "[entrypoint] fixing permissions for $dir"

  # Ensure directory exists
  mkdir -p "$dir"

  # Only chown if not already correct (avoids expensive full recursion)
  if ! stat -c '%U:%G' "$dir" 2>/dev/null | grep -q "^$USER:$GROUP$"; then
    chown -R "$USER:$GROUP" "$dir" || true
  fi

  # Ensure group writable with correct directory/file semantics
  chmod -R g+rwX "$dir" || true
}

# Symfony runtime directories
if [ -d "$TARGET_DIR/var" ]; then
  mkdir -p "$TARGET_DIR/var/cache/dev/profiler"
  fix_perms "$TARGET_DIR/var"
fi

# Uploads directory
UPLOAD_DIR="$TARGET_DIR/uploads"
mkdir -p "$UPLOAD_DIR"
fix_perms "$UPLOAD_DIR"

# If first arg is a flag (e.g. -F), prepend the default command
if [[ "${1:-}" == -* ]]; then
  set -- php-fpm "$@"
fi

# Run migrations and fixtures automatically unless opted out.
# SKIP_MIGRATIONS=1 will skip migrations.
# SKIP_FIXTURES=1 will skip fixtures.
if [ -f "$TARGET_DIR/bin/console" ]; then
  echo "[entrypoint] detected Symfony console"

  # Wait for database to be ready
  echo "[entrypoint] waiting for database to be ready"
  while ! php -r "
    try {
      \$pdo = new PDO('mysql:host=mysql;port=3306;dbname=vehicle_management', 'vehicle_user', 'vehicle_pass');
      echo 'OK';
    } catch (Exception \$e) {
      exit(1);
    }
  " 2>/dev/null | grep -q OK; do
    echo '[entrypoint] database not ready, waiting...'
    sleep 2
  done
  echo '[entrypoint] database is ready'

  if [ "${SKIP_MIGRATIONS:-0}" != "1" ]; then

  if [ "${SKIP_MIGRATIONS:-0}" != "1" ]; then
    echo "[entrypoint] running doctrine migrations (may take a moment)"
    (cd "$TARGET_DIR" && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration) || echo "[entrypoint] migrations failed or no migrations to run"
  else
    echo "[entrypoint] SKIP_MIGRATIONS=1 set; skipping migrations"
  fi

  if [ "${APP_ENV:-dev}" != "prod" ] && [ "${SKIP_FIXTURES:-0}" != "1" ]; then
    echo "[entrypoint] loading data fixtures (non-production only)"
    (cd "$TARGET_DIR" && php bin/console doctrine:fixtures:load --no-interaction) || echo "[entrypoint] fixtures load failed or already loaded"
  else
    echo "[entrypoint] skipping fixtures (production or SKIP_FIXTURES set)"
  fi

  # Ensure runtime permissions again after any work that may have created files
  if [ -d "$TARGET_DIR/var" ]; then
    fix_perms "$TARGET_DIR/var"
  fi
  fix_perms "$UPLOAD_DIR"
fi

exec "$@"
