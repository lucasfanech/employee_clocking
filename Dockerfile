# syntax=docker/dockerfile:1.7
#
# Multi-stage build:
#   assets  – compiles the Tailwind / Stimulus bundle with Node
#   php     – PHP-FPM runtime with Composer dependencies and the compiled assets
#   nginx   – static web server pointing at public/ (assets baked in)
#
ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ------------------------------------------------------------------ assets
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY webpack.config.js postcss.config.mjs ./
COPY assets ./assets
COPY templates ./templates
RUN npm run build

# ------------------------------------------------------------------ php base
FROM php:${PHP_VERSION}-fpm-alpine AS php_base

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN apk add --no-cache bash git icu-libs libzip mysql-client tzdata \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" intl opcache pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /app
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# ------------------------------------------------------------------ php (prod)
FROM php_base AS php

COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-progress --no-interaction --prefer-dist --no-autoloader

COPY --link . .
COPY --link --from=assets /app/public/build ./public/build

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script --no-dev post-install-cmd \
    && chmod +x bin/console \
    && mkdir -p var/cache var/log && chown -R www-data:www-data var

# ------------------------------------------------------------------ php (dev)
FROM php_base AS php_dev

ENV APP_ENV=dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps
COPY docker/php/conf.d/dev.ini /usr/local/etc/php/conf.d/dev.ini

# ------------------------------------------------------------------ nginx
FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
WORKDIR /app/public
COPY --from=php /app/public ./
