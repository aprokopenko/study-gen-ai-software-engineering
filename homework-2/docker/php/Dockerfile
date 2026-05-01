FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev libzip-dev unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html