# docker/php/Dockerfile
FROM php:8.4-fpm-bookworm

# Handle user permissions for local development added for WSL start
ARG UID=1000
ARG GID=1000
RUN usermod -u $UID www-data && groupmod -g $GID www-data
# Handle user permissions for local development added for WSL end

# system deps
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
     git unzip libonig-dev libxml2-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
     libssl-dev curl ca-certificates procps supervisor \
  && rm -rf /var/lib/apt/lists/*

# php extensions required by Laravel
RUN docker-php-ext-install pdo_mysql mbstring zip bcmath exif pcntl opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# gd (image)
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
  && docker-php-ext-install gd

#custom php.ini
COPY docker.dev/php/php.ini /usr/local/etc/php/conf.d/custom.ini

#supervisor config
COPY docker.dev/supervisor/laravel-worker.conf /etc/supervisor/conf.d/

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker.dev/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# set working directory
WORKDIR /var/www/html

#USER www-data

CMD ["/usr/local/bin/start.sh"]

