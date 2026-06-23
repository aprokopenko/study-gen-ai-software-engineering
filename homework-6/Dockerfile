# Dockerfile — homework-6 banking transaction-processing pipeline
#
# Base: official PHP 8.4 CLI image (current stable as of 2026-06-23).
# Coverage driver: pcov (lightweight, no debug overhead vs xdebug).
# Extensions for money/decimal: bcmath (arbitrary-precision math on strings).
# Composer is installed from the official Composer image.

FROM php:8.4-cli

# Install system dependencies needed to build PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgmp-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
#   bcmath  — arbitrary-precision math; used for all monetary string arithmetic
#   gmp     — required by some decimal libraries; useful fallback
#   pcov    — code coverage driver (lightweight alternative to xdebug)
RUN docker-php-ext-install bcmath gmp \
    && pecl install pcov \
    && docker-php-ext-enable pcov

# Install Composer from the official image (pinned to latest stable v2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Default command (overridden by docker compose / make targets)
CMD ["php", "-a"]
