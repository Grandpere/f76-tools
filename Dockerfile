# syntax=docker/dockerfile:1.7

FROM composer:2.8 AS deps
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        bash=5.3.3-r1 \
        freetype=2.14.1-r0 \
        icu-libs=76.1-r1 \
        libjpeg-turbo=3.1.2-r0 \
        libpng=1.6.55-r0 \
        libzip=1.11.4-r1 \
        libpq=18.2-r0 \
        libwebp=1.6.0-r0 \
    && apk add --no-cache --virtual .build-deps \
        autoconf=2.72-r1 \
        dpkg-dev=1.22.21-r0 \
        dpkg=1.22.21-r0 \
        file=5.46-r2 \
        freetype-dev=2.14.1-r0 \
        g++=15.2.0-r2 \
        gcc=15.2.0-r2 \
        musl-dev=1.2.5-r21 \
        make=4.4.1-r3 \
        pkgconf=2.5.1-r0 \
        re2c=4.3.1-r0 \
        icu-dev=76.1-r1 \
        libjpeg-turbo-dev=3.1.2-r0 \
        libpng-dev=1.6.55-r0 \
        libwebp-dev=1.6.0-r0 \
        libzip-dev=1.11.4-r1 \
        postgresql18-dev=18.2-r0 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_pgsql zip \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD pgrep -f "php-fpm: master process" > /dev/null || exit 1

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
WORKDIR /var/www/html
ENTRYPOINT ["app-entrypoint"]

FROM base AS dev
RUN apk add --no-cache \
        tesseract-ocr=5.5.1-r0 \
        tesseract-ocr-data-eng=5.5.1-r0 \
        tesseract-ocr-data-fra=5.5.1-r0 \
        tesseract-ocr-data-deu=5.5.1-r0
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=deps /app/vendor /var/www/html/vendor
COPY . /var/www/html
CMD ["php-fpm", "-F"]
