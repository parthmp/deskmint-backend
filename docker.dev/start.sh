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

#above is for native linux, try below for wsl ubuntu, not tested.
##/bin/bash##
# set -e

# # Ensure Laravel directories are writable
# chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
# chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# # Start supervisord (will manage workers, php-fpm, etc.)
# exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf

#run below command in container bash (for wsl) if it has issues with persmissions to access files, such as sending an email from queue. - tested
#access terminal of container for backend with "docker compose exec app bash" command.
#chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

#use below command in wsl if it won't allow you to edit files from the host - tested
#sudo chown -R user:user /home/stark/deskmint
