FROM php:8.4.13-apache-bookworm@sha256:9a2ce5c55ce38f31062e5027ef9cf0aa008c9f7571bd3362aeec5071ef28c06e

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080
