# syntax=docker/dockerfile:1.7
# Laravel API image: used by the php (php-fpm), horizon and scheduler services.
# Build context: repository root.

# ---------------------------------------------------------------- 1. vendor
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# Extensions (pcntl, gd, intl, redis...) exist in the runtime image, not in the composer image.
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
 && rm -rf tests .env .env.* database/*.sqlite storage/logs/* storage/framework/cache/data/* bootstrap/cache/*.php

# ---------------------------------------------------------------- 2. runtime
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache icu-libs libzip libpng freetype libjpeg-turbo fcgi tzdata \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd zip intl pcntl opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps \
 && rm -rf /tmp/pear

ENV TZ=America/Sao_Paulo
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/backend
COPY --from=vendor --chown=www-data:www-data /app ./
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
