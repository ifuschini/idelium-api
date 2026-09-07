FROM composer:2.8.10@sha256:20462d70afcfa999ad75dbd9333194067f4d869078bdb37430339e8d97e541d6 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

FROM php:8.4.13-apache-bookworm@sha256:9a2ce5c55ce38f31062e5027ef9cf0aa008c9f7571bd3362aeec5071ef28c06e

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
COPY --from=composer /app/vendor ./vendor
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080
