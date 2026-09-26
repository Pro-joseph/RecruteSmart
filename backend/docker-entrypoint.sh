#!/bin/sh
# Lot 0: bind mounts from Windows/macOS hosts arrive root-owned;
# php-fpm runs as www-data and needs writable storage.
set -eu
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Boot: migrate+seed on app start only (horizon/scheduler skip => no migration race).
# ponytail: firstOrCreate in the seeder makes re-running safe on every restart.
if [ "${1:-}" = "php-fpm" ]; then
  php artisan migrate --seed --force
  if [ -n "${ADMIN_EMAIL:-}" ]; then
    php artisan user:make-admin "$ADMIN_EMAIL" || echo "note: $ADMIN_EMAIL not registered yet (register, then re-run user:make-admin)" >&2
  fi
fi

exec "$@"
