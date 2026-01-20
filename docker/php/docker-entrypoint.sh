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

exec "$@"
