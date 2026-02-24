# syntax=docker/dockerfile:1.7

FROM composer:2.8 AS deps
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        bash \
        icu-libs \
        libzip \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html

FROM base AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=deps /app/vendor /var/www/html/vendor
COPY . /var/www/html
CMD ["php-fpm", "-F"]
