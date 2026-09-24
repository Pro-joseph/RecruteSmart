#!/bin/sh
# Lot 0: bind mounts from Windows/macOS hosts arrive root-owned;
# php-fpm runs as www-data and needs writable storage.
set -eu
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
exec "$@"
