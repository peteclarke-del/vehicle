#!/usr/bin/env bash

set -euo pipefail

# Run from the script directory (backend/)
cd "$(dirname "$0")" || exit 1

CONSOLE=./bin/console

if [ ! -x "$CONSOLE" ]; then
	echo "Error: $CONSOLE not found or not executable. Run composer install first." >&2
	exit 1
fi

FORCE=no
while [ "$#" -gt 0 ]; do
	case "$1" in
		-y|--yes) FORCE=yes; shift ;;
		-h|--help)
			echo "Usage: $(basename "$0") [-y|--yes]"
			echo
			echo "Regenerate the database: drop, create, generate migrations, migrate and load fixtures."
			exit 0
			;;
		*) echo "Unknown option: $1"; exit 1 ;;
	esac
done

if [ "$FORCE" != "yes" ]; then
	echo "This will DROP and recreate the database, then run migrations and load fixtures. Continue? [y/N]"
	read -r ans
	case "$ans" in
		[yY]|[yY][eE][sS]) ;;
		*) echo "Aborted."; exit 0 ;;
	esac
fi

echo "Dropping database (if exists)..."
"$CONSOLE" doctrine:database:drop --force --no-interaction || echo "drop returned non-zero status"

echo "Creating database..."
"$CONSOLE" doctrine:database:create --no-interaction

# Backup any existing auto-generated migration diffs before removing
if compgen -G "migrations/*.php" > /dev/null; then
	TS=$(date +%Y%m%d%H%M%S)
	BACKUP_DIR="migrations_backup_$TS"
	mkdir -p "$BACKUP_DIR"
	echo "Backing up existing migration files to $BACKUP_DIR"
	mv migrations/*.php "$BACKUP_DIR" || true
fi

echo "Generating migration diff (if schema changes exist)..."
if ! "$CONSOLE" doctrine:migrations:diff --no-interaction; then
	echo "No migration diff generated or diff failed; continuing." >&2
fi

echo "Running migrations..."
"$CONSOLE" doctrine:migrations:migrate --no-interaction

echo "Validating schema..."
"$CONSOLE" doctrine:schema:validate

echo "Loading fixtures..."
"$CONSOLE" doctrine:fixtures:load --no-interaction

echo "Database regenerated successfully."