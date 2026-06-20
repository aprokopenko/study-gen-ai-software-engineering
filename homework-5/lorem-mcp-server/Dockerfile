FROM php:8.4-cli

RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY server.php lorem-ipsum.md ./
COPY src/ src/

ENTRYPOINT ["php", "server.php"]
