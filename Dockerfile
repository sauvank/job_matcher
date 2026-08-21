FROM php:8.4-fpm-alpine

RUN apk add --no-cache git libpq-dev linux-headers poppler-utils unzip $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer run-script post-install-cmd \
    && php bin/console sass:build

CMD ["php-fpm"]
