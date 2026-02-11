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

  # Always recursively chown — sub-directories may have been created by
  # root (e.g. cache:clear, migrations) even if the top-level dir is correct.
  chown -R "$USER:$GROUP" "$dir" || true

  # Ensure group writable with correct directory/file semantics
  chmod -R g+rwX "$dir" || true
}

# Symfony runtime directories
if [ -d "$TARGET_DIR/var" ]; then
  mkdir -p "$TARGET_DIR/var/cache" "$TARGET_DIR/var/log"
  fix_perms "$TARGET_DIR/var"
fi

# Uploads directory
UPLOAD_DIR="$TARGET_DIR/uploads"
mkdir -p "$UPLOAD_DIR"
fix_perms "$UPLOAD_DIR"

# Generate JWT keypair if missing.
# Resolve key paths from env vars, replacing Symfony's %kernel.project_dir% placeholder.
JWT_SECRET_KEY="${JWT_SECRET_KEY:-%kernel.project_dir%/config/jwt/private.pem}"
JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-%kernel.project_dir%/config/jwt/public.pem}"
JWT_PRIVATE_PATH="${JWT_SECRET_KEY/\%kernel.project_dir\%/$TARGET_DIR}"
JWT_PUBLIC_PATH="${JWT_PUBLIC_KEY/\%kernel.project_dir\%/$TARGET_DIR}"
JWT_DIR="$(dirname "$JWT_PRIVATE_PATH")"
JWT_PASSPHRASE="${JWT_PASSPHRASE:-changeme}"

if [ ! -f "$JWT_PRIVATE_PATH" ] || [ ! -f "$JWT_PUBLIC_PATH" ]; then
  echo "[entrypoint] JWT keypair not found — generating ($JWT_PRIVATE_PATH, $JWT_PUBLIC_PATH)"
  mkdir -p "$JWT_DIR"
  openssl genpkey -out "$JWT_PRIVATE_PATH" -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass "pass:$JWT_PASSPHRASE"
  openssl pkey -in "$JWT_PRIVATE_PATH" -out "$JWT_PUBLIC_PATH" -pubout -passin "pass:$JWT_PASSPHRASE"
  chmod 644 "$JWT_PRIVATE_PATH" "$JWT_PUBLIC_PATH"
  chown "$USER:$GROUP" "$JWT_PRIVATE_PATH" "$JWT_PUBLIC_PATH"
  echo "[entrypoint] JWT keypair created"
fi

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
  
  # Parse DATABASE_URL into components.
  # Supports: mysql://, pgsql://, postgresql://, sqlite:///
  # Falls back to individual env vars if DATABASE_URL is not set.
  export DATABASE_URL="${DATABASE_URL:-mysql://${MYSQL_USER:-vehicle_user}:${MYSQL_PASSWORD:-vehicle_pass}@${DB_HOST:-mysql}:${DB_PORT:-3306}/${MYSQL_DATABASE:-vehicle_management}}"
  
  # PHP snippet that parses DATABASE_URL and builds a PDO connection
  read -r -d '' DB_PARSE_PHP << 'EOPHP' || true
    $url = getenv('DATABASE_URL') ?: $_ENV['DATABASE_URL'] ?? '';
    $parts = parse_url($url);
    $scheme = $parts['scheme'] ?? 'mysql';
    $host   = $parts['host'] ?? 'localhost';
    $port   = $parts['port'] ?? ($scheme === 'pgsql' || $scheme === 'postgresql' ? 5432 : 3306);
    $user   = rawurldecode($parts['user'] ?? 'root');
    $pass   = rawurldecode($parts['pass'] ?? '');
    $dbname = ltrim($parts['path'] ?? '', '/');
    // Normalise driver name
    $driver = match($scheme) {
      'mysql', 'mysql2', 'mariadb' => 'mysql',
      'pgsql', 'postgresql', 'postgres' => 'pgsql',
      'sqlite', 'sqlite3' => 'sqlite',
      default => $scheme,
    };
EOPHP

  while ! php -r "
    $DB_PARSE_PHP
    fwrite(STDERR, '[entrypoint] waiting for server: ' . \$driver . '://' . \$user . ':***@' . \$host . ':' . \$port . PHP_EOL);
    try {
      if (\$driver === 'sqlite') {
        \$pdo = new PDO('sqlite:' . \$dbname);
      } else {
        // Connect to the server without specifying the target database,
        // so the wait loop succeeds even if the database doesn't exist yet.
        \$defaultDb = (\$driver === 'pgsql') ? 'postgres' : '';
        \$dsn = \$driver . ':host=' . \$host . ';port=' . \$port . (\$defaultDb ? ';dbname=' . \$defaultDb : '');
        \$pdo = new PDO(\$dsn, \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
      }
      echo 'OK';
    } catch (Exception \$e) {
      fwrite(STDERR, '[entrypoint] PDO error: ' . \$e->getMessage() . PHP_EOL);
      exit(1);
    }
  " | grep -q OK; do
    echo '[entrypoint] database server not ready, waiting...'
    sleep 2
  done
  echo '[entrypoint] database server is ready'

  # Install composer dependencies if `vendor` is missing or empty or missing autoload
  VENDOR_DIR="$TARGET_DIR/vendor"
  VENDOR_AUTOLOAD="$VENDOR_DIR/autoload.php"
  vendor_empty=false
  if [ -d "$VENDOR_DIR" ]; then
    if [ -z "$(ls -A "$VENDOR_DIR")" ]; then
      vendor_empty=true
    fi
  fi

  if [ ! -d "$VENDOR_DIR" ] || [ ! -f "$VENDOR_AUTOLOAD" ] || [ "$vendor_empty" = true ]; then
    if command -v composer >/dev/null 2>&1; then
      echo "[entrypoint] vendor directory missing or empty - installing composer dependencies"
      # Choose flags based on environment.
      if [ "${APP_ENV:-dev}" = "prod" ]; then
        COMPOSER_FLAGS=(--no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts)
      else
        COMPOSER_FLAGS=(--no-interaction --prefer-dist --no-scripts)
      fi
      (cd "$TARGET_DIR" && composer install "${COMPOSER_FLAGS[@]}") || echo "[entrypoint] composer install failed"
    else
      echo "[entrypoint] composer not available in image; skipping install"
    fi
  fi

  if [ "${SKIP_MIGRATIONS:-0}" != "1" ]; then
    echo "[entrypoint] ensuring database exists"
    (cd "$TARGET_DIR" && php bin/console doctrine:database:create --if-not-exists --no-interaction) || echo "[entrypoint] database create failed (may already exist)"

    echo "[entrypoint] running doctrine migrations (may take a moment)"
    (cd "$TARGET_DIR" && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration) || echo "[entrypoint] migrations failed or no migrations to run"
  else
    echo "[entrypoint] SKIP_MIGRATIONS=1 set; skipping migrations"
  fi

  if [ "${APP_ENV:-dev}" != "prod" ] && [ "${SKIP_FIXTURES:-0}" != "1" ] || [ "${LOAD_FIXTURES:-0}" = "1" ]; then
    # Check if fixtures have already been loaded by checking if vehicle_types table has data
    fixtures_loaded=$(php -r "
      $DB_PARSE_PHP
      try {
        if (\$driver === 'sqlite') {
          \$pdo = new PDO('sqlite:' . \$dbname);
        } else {
          \$dsn = \$driver . ':host=' . \$host . ';port=' . \$port . ';dbname=' . \$dbname;
          \$pdo = new PDO(\$dsn, \$user, \$pass);
        }
        \$stmt = \$pdo->query('SELECT COUNT(*) FROM vehicle_types');
        \$count = \$stmt->fetchColumn();
        echo \$count > 0 ? 'yes' : 'no';
      } catch (Exception \$e) {
        echo 'no';
      }
    " 2>/dev/null)
    
    if [ "$fixtures_loaded" = "yes" ]; then
      echo "[entrypoint] fixtures already loaded, skipping"
    else
      echo "[entrypoint] loading data fixtures"

      # Build fixture flags.
      # When LOAD_FIXTURES=1 (demo/prod), load only the demo group.
      FIXTURE_FLAGS=(--no-interaction --append)
      if [ "${LOAD_FIXTURES:-0}" = "1" ]; then
        FIXTURE_FLAGS+=(--group=demo)
      fi

      (cd "$TARGET_DIR" && FORCE_FIXTURES=1 php bin/console doctrine:fixtures:load "${FIXTURE_FLAGS[@]}") || echo "[entrypoint] fixtures load failed"
    fi
  else
    echo "[entrypoint] skipping fixtures (production and LOAD_FIXTURES not set)"
  fi

  # Ensure runtime permissions again after any work that may have created files
  # Clear and warm the cache so it's fully built before php-fpm starts
  echo "[entrypoint] clearing and warming cache"
  (cd "$TARGET_DIR" && php bin/console cache:clear --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null) || true
  (cd "$TARGET_DIR" && php bin/console cache:warmup --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null) || true

  if [ -d "$TARGET_DIR/var" ]; then
    fix_perms "$TARGET_DIR/var"
  fi
  fix_perms "$UPLOAD_DIR"
fi

exec "$@"
