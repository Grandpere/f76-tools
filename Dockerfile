# syntax=docker/dockerfile:1.7

FROM composer:2.8 AS deps
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        bash=5.3.3-r1 \
        icu-libs=76.1-r1 \
        libzip=1.11.4-r1 \
        libpq=18.2-r0 \
    && apk add --no-cache --virtual .build-deps \
        autoconf=2.72-r1 \
        dpkg-dev=1.22.21-r0 \
        dpkg=1.22.21-r0 \
        file=5.46-r2 \
        g++=15.2.0-r2 \
        gcc=15.2.0-r2 \
        musl-dev=1.2.5-r21 \
        make=4.4.1-r3 \
        pkgconf=2.5.1-r0 \
        re2c=4.3.1-r0 \
        icu-dev=76.1-r1 \
        libzip-dev=1.11.4-r1 \
        postgresql18-dev=18.2-r0 \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD pgrep -x php-fpm > /dev/null || exit 1

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html

FROM base AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=deps /app/vendor /var/www/html/vendor
COPY . /var/www/html
CMD ["php-fpm", "-F"]
