ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache linux-headers oniguruma-dev \
    && docker-php-ext-install mbstring

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock phpunit.xml phpstan.neon ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY src/ ./src/
COPY tests/ ./tests/
