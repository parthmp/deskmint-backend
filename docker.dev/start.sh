#!/bin/bash
set -e

# Ensure directories exist
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Start PHP-FPM in background
php-fpm -D

# Start supervisor in foreground
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf