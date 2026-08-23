FROM php:8.4-fpm-alpine AS php_base

RUN apk add --no-cache git icu-libs libpq-dev libzip poppler-utils unzip \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install intl opcache pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN mkdir -p /run/cv-extractor \
    && chown www-data:www-data /run/cv-extractor

FROM php_base AS php_prod

ENV APP_ENV=prod
ENV APP_DEBUG=0

COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-app-prod.ini

COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-dev --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction \
    && composer run-script post-install-cmd \
    && php bin/console sass:build \
    && php bin/console asset-map:compile \
    && mkdir -p var/cache var/log var/cv \
    && chown -R www-data:www-data var

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx_prod

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=php_prod /app/public /app/public

FROM php_base AS php_dev

COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer run-script post-install-cmd \
    && php bin/console sass:build

CMD ["php-fpm"]

FROM php_dev AS php
