#!/bin/bash
set -e

# Auto-install dependencies if vendor doesn't exist
# if [ ! -d "/var/www/html/vendor" ]; then
#     composer install --no-interaction
# fi

# Ensure Laravel directories are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

supervisord

/usr/local/sbin/php-fpm -F