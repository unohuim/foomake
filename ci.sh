#!/usr/bin/env bash
set -euo pipefail

# --- Ensure we have a CI env file ---
if [ ! -f .env.ci ]; then
  cp .env.example .env.ci
fi

# --- Install PHP dependencies ---
composer install --no-interaction --prefer-dist --optimize-autoloader

# --- App key (CI) ---
php artisan key:generate --env=ci --force

# --- Permissions (safe no-op if not needed) ---
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# --- Frontend build ---
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

# --- OPTIONAL: DB migrate if CI DB is configured (Postgres) ---
# Only runs if DB_CONNECTION is present in .env.ci or your shell env.
if grep -qE '^\s*DB_CONNECTION=' .env.ci || [ -n "${DB_CONNECTION:-}" ]; then
  php artisan migrate --env=ci --force || true
fi

# --- Run tests ---
php artisan test --env=ci
