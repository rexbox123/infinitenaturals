# syntax=docker/dockerfile:1
#
# Infinite Naturals — production image (nginx + PHP-FPM, runs as non-root www-data).
# The same image runs the web app, the queue worker and the scheduler
# (see docker-compose.yml). Build with: docker compose build

ARG PHP_VERSION=8.4

############################################
# 1. PHP dependencies (no dev packages)
############################################
FROM serversideup/php:${PHP_VERSION}-cli AS vendor
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

############################################
# 2. Front-end assets (Vite + Tailwind)
############################################
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
# Tailwind scans Blade views (and Laravel's pagination views in vendor/).
COPY --from=vendor /var/www/html /app
RUN npm run build

############################################
# 3. Runtime
############################################
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS runtime

USER root
RUN install-php-extensions intl

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=20M \
    PHP_POST_MAX_SIZE=80M \
    AUTORUN_ENABLED=false \
    SSL_MODE=off

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

USER www-data
EXPOSE 8080
